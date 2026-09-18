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

    public function index(Request $request)
    {
        $users = $this->trainerService->getAllPaginated($request->query());

        return Inertia::render('Trainer/List', [
            'users' => $users,
            'filters' => $request->query(),
        ]);
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
            'action' => ['required', 'in:cancel'],
        ], [
            'salary_ids.required' => __('backend_messages.select_least_one_salary'),
            'salary_ids.array' => __('backend_messages.selected_salary_data_invalid'),
            'salary_ids.min' => __('backend_messages.select_least_one_salary'),
            'salary_ids.*.exists' => __('backend_messages.selected_salary_not_found'),
            'action.required' => __('backend_messages.action_required'),
            'action.in' => __('backend_messages.selected_action_invalid'),
        ]);

        $this->trainerService->updateMonthlySalaryStatuses($id, $validated['salary_ids'], $validated['action']);

        return redirect()
            ->route('trainer.salary', ['locale' => $locale, 'id' => $id])
            ->with('success', __('backend_messages.salary_statuses_updated'));
    }

    public function transferSalary(Request $request, string $locale, int $id)
    {
        $validated = $request->validate([
            'salary_id' => ['required', 'integer', 'exists:trainer_monthly_salaries,id'],
        ], [
            'salary_id.required' => __('backend_messages.select_salary_transfer'),
            'salary_id.integer' => __('backend_messages.selected_salary_invalid'),
            'salary_id.exists' => __('backend_messages.selected_salary_not_found'),
        ]);

        $this->trainerService->transferMonthlySalary($id, (int) $validated['salary_id']);

        return redirect()
            ->route('trainer.salary', ['locale' => $locale, 'id' => $id])
            ->with('success', __('backend_messages.salary_transferred_new_trainer_successfully'));
    }

    public function update(StoreTrainerScheduleRequest $request, string $locale, int $id)
    {
        $this->trainerService->saveTrainerScheduleData(
            $id,
            $request->validated()
        );

        return redirect()
            ->route('trainer.edit', ['locale' => $locale, 'id' => $id])
            ->with('success', __('backend_messages.updated'));
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
            ->with('success', __('backend_messages.saved'));
    }
}
