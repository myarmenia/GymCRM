<?php

namespace App\Http\Controllers\Schedule;

use App\DTO\schedule\WorkTimeManagmentDto;
use App\Helpers\MyHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ProductEditRequest;
use App\Http\Requests\Products\ProductStoreRequest;
use App\Http\Requests\Schedule\WorkTimeManagmentRequest;
use App\Services\Category\CategoryService;
use App\Services\MeasurementUnit\MeasurementUnitService;
use App\Services\Products\ProductsService;
use App\Services\Schedule\ScheduleService;
use App\Services\Warehouses\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    public function __construct(protected ScheduleService $scheduleService) {}

    public function index()
    {
        $data = $this->scheduleService->getAll();
        $locale = app()->getLocale();
        //dd(auth()->user()->getRoleNames());
        return Inertia::render('Schedule/Index', [
            'data' => $data,
            'locale' => $locale,
            'authUserRoles' => auth()->user()->getRoleNames(),
        ]);
    }
    public function create(string $locale, int $perPage = 100)
    {
        $weekdays = MyHelper::week_days();


        return Inertia::render('Schedule/Create', [
            'locale' => $locale,
            'weekdays' => $weekdays,
            'authUserRoles' => auth()->user()->getRoleNames(),
        ]);
    }

    public function store(WorkTimeManagmentRequest $request)
    {

        $dto = WorkTimeManagmentDto::fromRequest($request);

        $gym_id = MyHelper::find_auth_user_client();
        //dd($gym_id);
        $this->scheduleService->store($dto,  $gym_id);
        $data = $this->scheduleService->getAll();

        return Inertia::render('Schedule/Index', [
            'data' => $data,
            'locale' => app()->getLocale(),
            'authUserRoles' => auth()->user()->getRoleNames(),
        ])->with('success', 'Schedule created successfully.');
    }
    public function edit($locale, $id)
    {
        //dd($id);
        $weekdays = MyHelper::week_days();

        try {
            $this->scheduleService->ensureScheduleCanBeModified((int) $id);
            $data = $this->scheduleService->editScheduleName($id);
        } catch (ValidationException $e) {
            return redirect()
                ->route('schedule.index', ['locale' => $locale])
                ->withErrors($e->errors());
        }

        return Inertia::render('Schedule/Edit', [
            'data' => $data,
            'weekdays' => $weekdays,
            'authUserRoles' => auth()->user()->getRoleNames(),
            'locale' => app()->getLocale(),
        ]);
    }
    public function update(WorkTimeManagmentRequest $request, $locale, $id)
    {
        //dd($id);
        $dto = WorkTimeManagmentDto::fromRequest($request);

        $gymId = MyHelper::find_auth_user_client();

        $this->scheduleService->update($id, $dto, $gymId);

        return redirect()->route('schedule.edit', [
            'locale' => app()->getLocale(),
            'id' => $id,
        ]);
    }

    public function destroy($locale, $id)
    {
        try {
            $this->scheduleService->destroy((int) $id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('schedule.index', ['locale' => $locale])
            ->with('success', 'Schedule deleted successfully.');
    }
}
