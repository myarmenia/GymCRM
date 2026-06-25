<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('adminEntryCodes.{clientId}', function ($user, $clientId) {
    return (int) $user?->client->id === (int) $clientId;
});

//==========================
Broadcast::channel('turnstile.{clientId}', function ($user, $clientId) {
    $isManager = method_exists($user, 'hasRole') && $user->hasRole('manager');

    if (!$isManager && isset($user->role_name)) {
        $isManager = $user->role_name === 'manager';
    }

    if (!$isManager && isset($user->role)) {
        $role = $user->role;
        $isManager = is_object($role)
            ? ($role->name ?? null) === 'manager'
            : $role === 'manager';
    }

    if (!$isManager) {
        return false;
    }

    return (int) $user->gym_id === (int) $clientId;
});
//==========================
