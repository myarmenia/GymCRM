<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\StoreNotificationRequest;
use App\Services\Notifications\NotificationService;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    public function index()
    {
        $user = auth()->user();

        return Inertia::render('Notifications/List', [
            'notifications' => $this->notificationService->receivedForUser($user),
            'unreadCount' => $this->notificationService->unreadCount($user),
        ]);
    }

    public function create()
    {
        $user = auth()->user();

        return Inertia::render('Notifications/Create', [
            'users' => $this->notificationService->usersForSelect($user),
            'people' => $this->notificationService->peopleForSelect(),
        ]);
    }

    public function store(StoreNotificationRequest $request, string $locale)
    {
        $count = $this->notificationService->create(auth()->user(), $request->validated());

        return redirect()
            ->route('notifications.index', ['locale' => $locale])
            ->with('success', "Ուղարկվել է {$count} ծանուցում։");
    }

    public function destroyAll(string $locale)
    {
        $count = $this->notificationService->deleteAllReceived(auth()->user());

        return back()->with('success', "Ջնջվել է {$count} ծանուցում։");
    }

    public function destroy(string $locale, int $notification)
    {
        $deleted = $this->notificationService->deleteReceived(auth()->user(), $notification);

        return back()->with(
            $deleted ? 'success' : 'error',
            $deleted ? 'Ծանուցումը ջնջվել է։' : 'Ծանուցումը չի գտնվել։'
        );
    }
}
