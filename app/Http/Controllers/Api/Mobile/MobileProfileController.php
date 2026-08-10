<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\MobileProfile\UpdateMobileProfileRequest;
use App\Http\Requests\MobileProfile\UpdatePersonBiometricRequest;
use App\Http\Resources\Mobile\PersonBiometricResource;
use App\Http\Resources\Mobile\PersonResource;
use App\Models\Person;
use App\Services\MobileProfile\MobilePersonProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileProfileController extends Controller
{
    public function __construct(protected MobilePersonProfileService $profileService)
    {
    }

    public function update(UpdateMobileProfileRequest $request): JsonResponse
    {
        $person = $this->profileService->updateProfile(
            $this->person($request),
            $request->validated(),
        );

        return response()->json(['data' => new PersonResource($person)]);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $this->profileService->deactivate($this->person($request));

        return response()->json(['data' => ['deactivated' => true]]);
    }

    public function biometric(Request $request): JsonResponse
    {
        $biometric = $this->profileService->biometric($this->person($request));

        return response()->json([
            'data' => $biometric ? new PersonBiometricResource($biometric) : null,
        ]);
    }

    public function updateBiometric(UpdatePersonBiometricRequest $request): JsonResponse
    {
        $biometric = $this->profileService->updateBiometric(
            $this->person($request),
            $request->validated(),
        );

        return response()->json(['data' => new PersonBiometricResource($biometric)]);
    }

    private function person(Request $request): Person
    {
        return $request->user();
    }
}
