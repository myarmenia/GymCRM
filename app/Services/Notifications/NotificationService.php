<?php

namespace App\Services\Notifications;

use App\Events\UserNotificationCreated;
use App\Interfaces\Notifications\NotificationRepositoryInterface;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(
        protected NotificationRepositoryInterface $notificationRepository,
    ) {}

    public function receivedForUser(User $user): LengthAwarePaginator
    {
        $notifications = $this->notificationRepository->receivedForUser($user);

        $unreadIds = $notifications->getCollection()
            ->filter(fn (Notification $notification) => !$notification->seen)
            ->pluck('id');

        $notifications->getCollection()->transform(function (Notification $notification) {
            $notification->setAttribute('was_unread', !$notification->seen);

            return $notification;
        });

        $this->markSeen($unreadIds);

        return $notifications;
    }

    public function unreadCount(User $user): int
    {
        return $this->notificationRepository->unreadCount($user);
    }

    public function markSeen(Collection $ids): void
    {
        $this->notificationRepository->markSeenByIds($ids);
    }

    public function create(User $sender, array $data): int
    {
        $recipientIds = $this->resolveRecipientIds($sender, $data);

        if ($recipientIds->isEmpty()) {
            return 0;
        }

        $createdIds = collect();
        $now = now();

        DB::transaction(function () use ($recipientIds, $sender, $data, $now, &$createdIds) {
            $recipientIds->chunk(50)->each(function (Collection $chunk) use ($sender, $data, $now, &$createdIds) {
                $rows = $chunk->map(fn (int $recipientId) => [
                    'sender_id' => $sender->id,
                    'recipient_id' => $recipientId,
                    'about_id' => $data['about_id'] ?? null,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'seen' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                $this->notificationRepository->insertMany($rows);

                $createdIds = $createdIds->merge(
                    $this->notificationRepository->idsForInsertedRows(
                        $sender,
                        $chunk,
                        $data['title'],
                        $data['description'],
                        $now
                    )
                );
            });
        });

        $this->broadcastCreatedNotifications($createdIds);

        return $createdIds->count();
    }

    public function usersForSelect(User $sender): Collection
    {
        return $this->notificationRepository->usersForSelect($sender);
    }

    public function peopleForSelect(): Collection
    {
        return $this->notificationRepository->peopleForSelect();
    }

    public function deleteReceived(User $user, int $notificationId): bool
    {
        return $this->notificationRepository->deleteReceivedById($user, $notificationId);
    }

    public function deleteAllReceived(User $user): int
    {
        return $this->notificationRepository->deleteAllReceived($user);
    }

    protected function resolveRecipientIds(User $sender, array $data): Collection
    {
        if (!empty($data['send_to_all'])) {
            return $this->notificationRepository
                ->userIdsExcept($sender->id)
                ->unique()
                ->values();
        }

        return collect($data['recipient_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id !== $sender->id)
            ->unique()
            ->values();
    }

    protected function broadcastCreatedNotifications(Collection $createdIds): void
    {
        $this->notificationRepository
            ->getWithRelationsByIds($createdIds)
            ->chunk(100)
            ->each(function (Collection $notifications) {
                $unreadCounts = $this->notificationRepository->unreadCountsForRecipients(
                    $notifications->pluck('recipient_id')->unique()
                );

                $notifications->each(function (Notification $notification) use ($unreadCounts) {
                    broadcast(new UserNotificationCreated(
                        $notification,
                        (int) ($unreadCounts[$notification->recipient_id] ?? 0)
                    ));
                });
            });
    }
}
