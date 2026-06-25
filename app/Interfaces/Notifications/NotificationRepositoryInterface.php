<?php

namespace App\Interfaces\Notifications;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    public function receivedForUser(User $user, int $perPage = 20): LengthAwarePaginator;

    public function unreadCount(User $user): int;

    public function markSeenByIds(Collection $ids): void;

    public function insertMany(array $rows): void;

    public function idsForInsertedRows(User $sender, Collection $recipientIds, string $title, string $description, $createdAt): Collection;

    public function getWithRelationsByIds(Collection $ids): Collection;

    public function unreadCountsForRecipients(Collection $recipientIds): Collection;

    public function userIdsExcept(int $userId): Collection;

    public function usersForSelect(User $sender): Collection;

    public function peopleForSelect(int $limit = 500): Collection;

    public function deleteReceivedById(User $user, int $notificationId): bool;

    public function deleteAllReceived(User $user): int;
}
