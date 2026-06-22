<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\StoreTrainerScheduleRequest;
use App\Services\EntryCodes\EntryCodeService;
use App\Services\Gyms\GymService;
use App\Services\Roles\RoleService;
use App\Services\Schedule\ScheduleService;
use App\Services\Trainer\TrainerService;
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

    public function index()
    {
        $users = $this->trainerService->getAllPaginated();

        return Inertia::render('Trainer/List', ['users' => $users]);
    }

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

    public function salary(string $locale, int $id)
    {
        return Inertia::render('Trainer/Salary', $this->trainerService->salaryPageData($id));
    }

    public function updateSalaryStatus(Request $request, string $locale, int $id)
    {
        $validated = $request->validate([
            'salary_ids' => ['required', 'array', 'min:1'],
            'salary_ids.*' => ['integer', 'exists:trainer_monthly_salaries,id'],
            'action' => ['required', 'in:pay,cancel'],
        ], [
            'salary_ids.required' => 'Ընտրեք առնվազն մեկ աշխատավարձ։',
            'salary_ids.array' => 'Ընտրված աշխատավարձերի տվյալները սխալ են։',
            'salary_ids.min' => 'Ընտրեք առնվազն մեկ աշխատավարձ։',
            'salary_ids.*.exists' => 'Ընտրված աշխատավարձը չի գտնվել։',
            'action.required' => 'Գործողությունը պարտադիր է։',
            'action.in' => 'Ընտրված գործողությունը սխալ է։',
        ]);

        $this->trainerService->updateMonthlySalaryStatuses($id, $validated['salary_ids'], $validated['action']);

        return redirect()
            ->route('trainer.salary', ['locale' => $locale, 'id' => $id])
            ->with('success', 'Աշխատավարձերի կարգավիճակը թարմացվեց։');
    }

    public function transferSalary(Request $request, string $locale, int $id)
    {
        $validated = $request->validate([
            'salary_id' => ['required', 'integer', 'exists:trainer_monthly_salaries,id'],
        ], [
            'salary_id.required' => 'Ընտրեք փոխանցվող աշխատավարձը։',
            'salary_id.integer' => 'Ընտրված աշխատավարձը սխալ է։',
            'salary_id.exists' => 'Ընտրված աշխատավարձը չի գտնվել։',
        ]);

        $this->trainerService->transferMonthlySalary($id, (int) $validated['salary_id']);

        return redirect()
            ->route('trainer.salary', ['locale' => $locale, 'id' => $id])
            ->with('success', 'Աշխատավարձը հաջողությամբ փոխանցվեց նոր մարզչին։');
    }

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
        $roles = $user->roles->pluck('name')->toArray();
        $selectedEntryCodeId = $user->entryPermissions()->first()?->entry_code_id ?? null;

        return Inertia::render('Trainer/Show', [
            'user' => $user,
            'roles' => $roles,
            'selectedEntryCodeId' => $selectedEntryCodeId,
            'canSelectGym' => $authUser->hasRole('owner'),
        ]);
    }

    public function store(StoreTrainerScheduleRequest $request, string $locale, int $id)
    {
        $this->trainerService->saveTrainerScheduleData(
            $id,
            $request->validated()
        );

        return redirect()
            ->route('trainer.edit', ['locale' => $locale, 'id' => $id])
            ->with('success', 'Պահպանվեց');
    }
}
