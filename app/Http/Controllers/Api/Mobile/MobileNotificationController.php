<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\MobileNotificationResource;
use App\Models\Person;
use App\Services\MobileNotifications\MobileNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileNotificationController extends Controller
{
    public function __construct(protected MobileNotificationService $notificationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => MobileNotificationResource::collection(
                $this->notificationService->allForPerson($this->person($request)),
            ),
        ]);
    }

    public function markAsRead(Request $request, int $notification): JsonResponse
    {
        $item = $this->notificationService->markAsRead(
            $this->person($request),
            $notification,
        );

        abort_if(!$item, 404);

        return response()->json([
            'data' => new MobileNotificationResource($item),
        ]);
    }

    private function person(Request $request): Person
    {
        return $request->user();
    }
}
