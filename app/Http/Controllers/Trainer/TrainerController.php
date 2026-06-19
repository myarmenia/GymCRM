<?php

namespace App\Http\Controllers\Trainer;

use App\DTO\User\UserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\StoreTrainerScheduleRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Services\EntryCodes\EntryCodeService;
use App\Services\Gyms\GymService;
use App\Services\Roles\RoleService;
use App\Services\Schedule\ScheduleService;
use App\Services\Trainer\TrainerService;
use App\Services\Users\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TrainerController extends Controller
{
    public function __construct(
        protected TrainerService $trainerService,
        protected RoleService $roleService,
        protected GymService $gymService,
        protected EntryCodeService $entryCodeService,
        protected ScheduleService $scheduleService

    ) {}

    // ========== list =====================
    public function index()
    {

        $users = $this->trainerService->getAllPaginated();

        return Inertia::render('Trainer/List', ['users' => $users]);
    }


    //public function edit(string $locale, int $id)
    //{
    //    $authUser = Auth::user();
    //    $scheduleNames = $this->scheduleService->getAllScheduleNamesForGym($authUser->gym_id);
    //    $trainer = $this->trainerService->getById($id); // կամ կարող եք ստեղծել նոր Trainer օբյեկտ, եթե անհրաժեշտ է
    //    $trainerSessionDuration = $this->trainerService->getTrainerSessionDuration($id);
    //    $trainerScheduleIds = $trainerSessionDuration
    //        ? [$trainerSessionDuration->schedule_name_id]
    //        : [];
    //    //dd($scheduleNames, $trainer, $trainerSessionDuration, $trainerScheduleIds);
    //    return Inertia::render('Trainer/Edit', [
    //        'scheduleNames' => $scheduleNames,
    //        'trainer' => $trainer,
    //        'trainerSessionDuration' => $trainerSessionDuration,
    //        'trainerScheduleIds' => $trainerScheduleIds,
    //    ]);
    //}
    public function edit(string $locale, int $id)
    {
        $authUser = Auth::user();

        $scheduleNames = $this->scheduleService
            ->getAllScheduleNamesForGym($authUser->gym_id);

        $trainer = $this->trainerService->getById($id);

        $trainerSessionDuration = $this->trainerService
            ->getTrainerSessionDuration($id);

        $trainerScheduleIds = $trainerSessionDuration
            ->pluck('schedule_name_id')
            ->toArray();

        return Inertia::render('Trainer/Edit', [
            'scheduleNames' => $scheduleNames,
            'trainer' => $trainer,
            'trainerSessionDuration' => $trainerSessionDuration,
            'trainerScheduleIds' => $trainerScheduleIds,
        ]);
    }

    public function profile(string $locale, int $id)
    {
        return Inertia::render('Trainer/Profile', $this->trainerService->profileData($id));
    }



    // ========== update =====================
    public function update(StoreTrainerScheduleRequest $request, string $locale, int $id)
    {
        $this->trainerService->saveTrainerScheduleData(
            $id,
            $request->validated()
        );

        return redirect()
            ->route('trainer.edit', ['locale' => $locale, 'id' => $id])
            ->with('success', 'Թարմացվեց');
    }


    public function show($locale, $userId)
    {
        $user = $this->trainerService->getById($userId);
        $authUser = Auth::user();

        // Կարող եք ավելացնել աութորիզացիա (օրինակ՝ միայն owner կամ sales_manager)
        // if ($authUser->cannot('view', $user)) abort(403);

        $roles = $user->roles->pluck('name')->toArray(); // միայն դերերի անուններ
        $selectedEntryCodeId = $user->entryPermissions()->first()?->entry_code_id ?? null;

        return Inertia::render('Trainer/Show', [
            'user' => $user,
            'roles' => $roles,
            'selectedEntryCodeId' => $selectedEntryCodeId,
            'canSelectGym' => $authUser->hasRole('owner'), // եթե անհրաժեշտ է, կարող եք նաև gym-երի ցուցակը
        ]);
    }

    public function store(StoreTrainerScheduleRequest $request, string $locale, int $id)
    {
        //dd($request->validated());
        $this->trainerService->saveTrainerScheduleData(
            $id,
            $request->validated()
        );

        return redirect()
            ->route('trainer.edit', ['locale' => $locale, 'id' => $id])
            ->with('success', 'Պահպանվեց');
    }
}
