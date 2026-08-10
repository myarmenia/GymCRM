<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\MobileMembershipResource;
use App\Http\Resources\Mobile\MobileMembershipDetailResource;
use App\Models\Person;
use App\Services\MobileMemberships\MobileMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileMembershipController extends Controller
{
    public function __construct(protected MobileMembershipService $membershipService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => MobileMembershipResource::collection(
                $this->membershipService->allForPerson($this->person($request)),
            ),
        ]);
    }

    public function show(Request $request, int $membership): JsonResponse
    {
        $personMembership = $this->membershipService->findForPerson(
            $this->person($request),
            $membership,
        );

        abort_if($personMembership === null, 404);

        return response()->json([
            'data' => new MobileMembershipDetailResource($personMembership),
        ]);
    }

    private function person(Request $request): Person
    {
        return $request->user();
    }
}
