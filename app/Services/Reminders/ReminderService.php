<?php

namespace App\Services\Reminders;

use App\Models\MembershipSale;
use App\Models\Reminder;
use App\Models\ReminderCategory;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReminderService
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    public function create(User $creator, array $data): Reminder
    {
        $recipientIds = collect($data['recipient_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            throw ValidationException::withMessages([
                'recipient_ids' => 'Ընտրեք առնվազն մեկ ստացող։',
            ]);
        }

        return DB::transaction(function () use ($creator, $data, $recipientIds) {
            $gymId = $data['gym_id']
                ?? $creator->gym_id
                ?? User::query()->whereIn('id', $recipientIds)->value('gym_id');

            $reminder = Reminder::query()->create([
                'gym_id' => $gymId,
                'category_id' => $data['category_id'],
                'created_by' => $creator->id,
                'about_id' => $data['about_id'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'scheduled_at' => $data['scheduled_at'],
                'status' => 'scheduled',
            ]);

            $now = now();
            $reminder->recipients()->attach(
                $recipientIds->mapWithKeys(fn (int $userId) => [
                    $userId => [
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ])->all()
            );

            return $reminder->load(['category', 'creator', 'about', 'recipients']);
        });
    }

    public function createForMembershipDebt(
        User $creator,
        MembershipSale $sale,
        array $data,
        float $debtAmount,
    ): Reminder {
        $category = ReminderCategory::query()
            ->where('slug', 'membership_payment_due')
            ->firstOrFail();
        $personName = trim("{$sale->person->name} {$sale->person->surname}");

        return $this->create($creator, [
            'gym_id' => $sale->gym_id,
            'category_id' => $category->id,
            'recipient_ids' => $data['recipient_ids'] ?? [],
            'about_id' => $sale->person_id,
            'source_type' => 'membership_sale',
            'source_id' => $sale->id,
            'title' => $data['title'] ?? 'Աբոնեմենտի վճարման հիշեցում',
            'description' => $data['description']
                ?? "{$personName}-ի աբոնեմենտի մնացորդային վճարումը՝ "
                    .number_format($debtAmount, 2, '.', ' ').' դրամ։',
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);
    }

    public function categoriesForSelect(): Collection
    {
        return ReminderCategory::query()
            ->where('active', true)
            ->orderBy('id')
            ->get(['id', 'slug', 'name'])
            ->map(fn (ReminderCategory $category) => [
                'value' => $category->id,
                'slug' => $category->slug,
                'label' => $category->name,
            ]);
    }

    public function usersForSelect(User $actor): Collection
    {
        return User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'owner'))
            ->when(! $actor->hasRole('owner'), fn ($query) => $query->where('gym_id', $actor->gym_id))
            ->orderBy('name')
            ->orderBy('surname')
            ->get(['id', 'name', 'surname', 'email'])
            ->map(fn (User $user) => [
                'value' => $user->id,
                'label' => trim("{$user->name} {$user->surname}") ?: $user->email,
            ]);
    }

    public function defaultMembershipRecipients(User $actor): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['sales_manager', 'super_admin']))
            ->when(! $actor->hasRole('owner'), fn ($query) => $query->where('gym_id', $actor->gym_id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function scheduledForUser(User $user): LengthAwarePaginator
    {
        return Reminder::query()
            ->with(['category', 'creator', 'about', 'recipients'])
            ->where(function ($query) use ($user) {
                $query
                    ->where('created_by', $user->id)
                    ->orWhereHas('recipients', fn ($recipientQuery) => $recipientQuery->where('users.id', $user->id));
            })
            ->whereIn('status', ['scheduled', 'processing', 'failed'])
            ->latest('scheduled_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function cancel(User $actor, Reminder $reminder): void
    {
        $allowed = $reminder->created_by === $actor->id
            || $reminder->recipients()->where('users.id', $actor->id)->exists();

        if (! $allowed || ! in_array($reminder->status, ['scheduled', 'failed'], true)) {
            abort(403);
        }

        $reminder->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function cancelForMembershipSale(int $membershipSaleId): int
    {
        return Reminder::query()
            ->where('source_type', 'membership_sale')
            ->where('source_id', $membershipSaleId)
            ->whereIn('status', ['scheduled', 'failed'])
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
    }

    public function sendDue(): int
    {
        $sent = 0;

        Reminder::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $reminderId) use (&$sent) {
                if ($this->sendOne($reminderId)) {
                    $sent++;
                }
            });

        return $sent;
    }

    protected function sendOne(int $reminderId): bool
    {
        $claimed = DB::transaction(function () use ($reminderId) {
            $reminder = Reminder::query()->lockForUpdate()->find($reminderId);

            if (! $reminder || $reminder->status !== 'scheduled' || $reminder->scheduled_at->isFuture()) {
                return false;
            }

            $reminder->update(['status' => 'processing', 'last_error' => null]);

            return true;
        });

        if (! $claimed) {
            return false;
        }

        $reminder = Reminder::query()->with(['creator', 'recipients'])->findOrFail($reminderId);

        try {
            $recipientIds = $reminder->recipients->pluck('id')->map(fn ($id) => (int) $id);

            $this->notificationService->createForRecipientIds(
                $reminder->creator,
                [
                    'about_id' => $reminder->about_id,
                    'title' => $reminder->title,
                    'description' => $reminder->description,
                ],
                $recipientIds
            );

            DB::transaction(function () use ($reminder) {
                $now = now();
                DB::table('reminder_recipients')
                    ->where('reminder_id', $reminder->id)
                    ->update([
                        'status' => 'sent',
                        'sent_at' => $now,
                        'updated_at' => $now,
                    ]);
                $reminder->update([
                    'status' => 'sent',
                    'sent_at' => $now,
                    'last_error' => null,
                ]);
            });

            return true;
        } catch (Throwable $exception) {
            $reminder->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            report($exception);

            return false;
        }
    }
}
