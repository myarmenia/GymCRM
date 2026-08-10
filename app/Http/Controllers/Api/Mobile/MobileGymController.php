<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\GymResource;
use App\Models\Person;
use App\Services\MobileGyms\MobileGymService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileGymController extends Controller
{
    public function __construct(protected MobileGymService $gymService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => GymResource::collection($this->gymService->allForPerson($this->person($request))),
        ]);
    }

    private function person(Request $request): Person
    {
        return $request->user();
    }
}
