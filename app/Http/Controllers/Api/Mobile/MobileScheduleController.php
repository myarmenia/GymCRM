<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\MobileSchedule\MobileScheduleIndexRequest;
use App\Http\Resources\Mobile\MobileScheduleEventResource;
use App\Models\Person;
use App\Services\MobileSchedule\MobileScheduleService;
use Illuminate\Http\JsonResponse;

class MobileScheduleController extends Controller
{
    public function __construct(protected MobileScheduleService $scheduleService)
    {
    }

    public function index(MobileScheduleIndexRequest $request): JsonResponse
    {
        $schedule = $this->scheduleService->eventsForPerson(
            $this->person($request),
            $request->integer('gym_id') ?: null,
            $request->input('from'),
            $request->input('to'),
        );

        return response()->json([
            'data' => [
                'from' => $schedule['from'],
                'to' => $schedule['to'],
                'events' => MobileScheduleEventResource::collection($schedule['events']),
            ],
        ]);
    }

    private function person(MobileScheduleIndexRequest $request): Person
    {
        return $request->user();
    }
}
