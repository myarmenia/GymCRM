<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\MobileAuth\ChangeMobilePasswordRequest;
use App\Http\Requests\MobileAuth\MobileLoginRequest;
use App\Http\Requests\MobileAuth\MobileRegisterRequest;
use App\Http\Requests\MobileAuth\UpdateFcmTokenRequest;
use App\Http\Resources\Mobile\PersonResource;
use App\Models\Person;
use App\Services\MobileAuth\MobilePersonAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAuthController extends Controller
{
    public function __construct(protected MobilePersonAuthService $authService) {}

    public function login(MobileLoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('phone')->toString(),
            $request->string('password')->toString(),
            $request->input('fcm_token'),
            $request->input('device_name'),
        );

        return response()->json([
            'data' => [
                'access_token' => $result['access_token'],
                'token_type' => $result['token_type'],
                'person' => new PersonResource($result['person']),
            ],
        ]);
    }

    public function register(MobileRegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json(['data' => [
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
            'person' => new PersonResource($result['person']),
        ]], $result['created'] ? 201 : 200);
    }

    public function changePassword(ChangeMobilePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $this->person($request),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json(['data' => ['password_changed' => true]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($this->person($request));

        return response()->json(['data' => ['logged_out' => true]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new PersonResource($this->person($request)),
        ]);
    }

    public function updateFcmToken(UpdateFcmTokenRequest $request): JsonResponse
    {
        $person = $this->authService->updateFcmToken(
            $this->person($request),
            $request->string('fcm_token')->toString(),
        );

        return response()->json([
            'data' => [
                'person' => new PersonResource($person),
                'fcm_token_updated' => true,
            ],
        ]);
    }

    private function person(Request $request): Person
    {
        return $request->user();
    }
}
