<?php

namespace App\Services\MobileNotifications;

use App\Interfaces\MobileNotifications\MobileNotificationInterface;
use App\Models\MobileNotification;
use App\Models\Person;
use Illuminate\Support\Collection;

class MobileNotificationService
{
    public function __construct(protected MobileNotificationInterface $notifications)
    {
    }

    public function allForPerson(Person $person): Collection
    {
        return $this->notifications->allForPerson($person);
    }

    public function markAsRead(Person $person, int $notificationId): ?MobileNotification
    {
        return $this->notifications->markAsRead($person, $notificationId);
    }
}
