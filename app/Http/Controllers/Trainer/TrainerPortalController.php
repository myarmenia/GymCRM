<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\TrainerSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrainerPortalController extends Controller
{
    public function mySchedules(Request $request)
    {
        $trainer = $this->trainer($request);

        $schedules = TrainerSchedule::query()
            ->with([
                'schedule.schedule_details',
                'sessionDurations.slots',
            ])
            ->where('user_id', $trainer->id)
            ->get();

        return Inertia::render('Trainer/MySchedules', [
            'schedules' => $schedules,
        ]);
    }

    public function myCustomers(Request $request)
    {
        $trainer = $this->trainer($request);

        $assignedMemberships = function ($query) use ($trainer): void {
            $query
                ->where('trainer_id', $trainer->id)
                ->whereIn('status', ['waiting', 'active', 'frozen'])
                ->with('membershipPlan.translations')
                ->latest('id');
        };

        $customers = Person::query()
            ->whereHas('memberships', $assignedMemberships)
            ->with([
                'memberships' => $assignedMemberships,
            ])
            ->orderBy('name')
            ->orderBy('surname')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Trainer/MyCustomers', [
            'customers' => $customers,
        ]);
    }

    private function trainer(Request $request)
    {
        $user = $request->user();

        abort_unless($user?->hasRole('trainer'), 403);

        return $user;
    }
}
