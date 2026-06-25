<?php

namespace App\Repositories\Notifications;

use App\Interfaces\Notifications\NotificationRepositoryInterface;
use App\Models\Notification;
use App\Models\Person;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function receivedForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Notification::query()
            ->with(['sender', 'about'])
            ->where('recipient_id', $user->id)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()
            ->where('recipient_id', $user->id)
            ->where('seen', false)
            ->count();
    }

    public function markSeenByIds(Collection $ids): void
    {
        if ($ids->isEmpty()) {
            return;
        }

        Notification::query()
            ->whereIn('id', $ids)
            ->update(['seen' => true]);
    }

    public function insertMany(array $rows): void
    {
        Notification::query()->insert($rows);
    }

    public function idsForInsertedRows(User $sender, Collection $recipientIds, string $title, string $description, $createdAt): Collection
    {
        return Notification::query()
            ->where('sender_id', $sender->id)
            ->whereIn('recipient_id', $recipientIds)
            ->where('title', $title)
            ->where('description', $description)
            ->where('created_at', $createdAt)
            ->pluck('id');
    }

    public function getWithRelationsByIds(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return Notification::query()
            ->with(['sender', 'about'])
            ->whereIn('id', $ids)
            ->get();
    }

    public function unreadCountsForRecipients(Collection $recipientIds): Collection
    {
        if ($recipientIds->isEmpty()) {
            return collect();
        }

        return Notification::query()
            ->selectRaw('recipient_id, count(*) as unread_count')
            ->whereIn('recipient_id', $recipientIds)
            ->where('seen', false)
            ->groupBy('recipient_id')
            ->pluck('unread_count', 'recipient_id');
    }

    public function userIdsExcept(int $userId): Collection
    {
        return User::query()
            ->whereKeyNot($userId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    public function usersForSelect(User $sender): Collection
    {
        return User::query()
            ->whereKeyNot($sender->id)
            ->orderBy('name')
            ->orderBy('surname')
            ->get(['id', 'name', 'surname', 'email'])
            ->map(fn (User $user) => [
                'value' => $user->id,
                'label' => trim("{$user->name} {$user->surname}") ?: $user->email,
            ]);
    }

    public function peopleForSelect(int $limit = 500): Collection
    {
        return Person::query()
            ->orderBy('name')
            ->orderBy('surname')
            ->limit($limit)
            ->get(['id', 'name', 'surname', 'phone'])
            ->map(fn (Person $person) => [
                'value' => $person->id,
                'label' => trim("{$person->name} {$person->surname}") . ($person->phone ? " ({$person->phone})" : ''),
            ]);
    }

    public function deleteReceivedById(User $user, int $notificationId): bool
    {
        return (bool) Notification::query()
            ->whereKey($notificationId)
            ->where('recipient_id', $user->id)
            ->delete();
    }

    public function deleteAllReceived(User $user): int
    {
        return Notification::query()
            ->where('recipient_id', $user->id)
            ->delete();
    }
}
