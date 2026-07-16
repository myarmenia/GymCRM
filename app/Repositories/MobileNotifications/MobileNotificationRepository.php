<?php

namespace App\Repositories\MobileNotifications;

use App\Interfaces\MobileNotifications\MobileNotificationInterface;
use App\Models\MobileNotification;
use App\Models\Person;
use Illuminate\Support\Collection;

class MobileNotificationRepository implements MobileNotificationInterface
{
    public function allForPerson(Person $person): Collection
    {
        return MobileNotification::query()
            ->where('person_id', $person->id)
            ->latest('id')
            ->get();
    }

    public function markAsRead(Person $person, int $notificationId): ?MobileNotification
    {
        $notification = MobileNotification::query()
            ->where('person_id', $person->id)
            ->find($notificationId);

        if (!$notification) {
            return null;
        }

        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return $notification->fresh();
    }
}
