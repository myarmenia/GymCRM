<?php

namespace App\Interfaces\MobileNotifications;

use App\Models\MobileNotification;
use App\Models\Person;
use Illuminate\Support\Collection;

interface MobileNotificationInterface
{
    public function allForPerson(Person $person): Collection;

    public function markAsRead(Person $person, int $notificationId): ?MobileNotification;
}
