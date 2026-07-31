<?php

namespace App\Http\Controllers\Reminders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reminders\StoreReminderRequest;
use App\Models\Reminder;
use App\Services\Reminders\ReminderService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReminderController extends Controller
{
    public function __construct(
        protected ReminderService $reminderService,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('Notifications/Reminders', [
            'reminders' => $this->reminderService->scheduledForUser($request->user()),
        ]);
    }

    public function store(StoreReminderRequest $request, string $locale)
    {
        $this->reminderService->create($request->user(), $request->validated());

        return redirect()
            ->route('reminders.index', ['locale' => $locale])
            ->with('success', 'Հիշեցումը պլանավորվել է։');
    }

    public function cancel(Request $request, string $locale, Reminder $reminder)
    {
        $this->reminderService->cancel($request->user(), $reminder);

        return back()->with('success', 'Հիշեցումը չեղարկվել է։');
    }
}
