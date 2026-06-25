<?php

namespace App\Http\Controllers\Api\Turnstile;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\EntryExitSystemRequest;
use App\Services\Turnstile\EntryExitSystemService;
use App\DTO\Turnstile\EntryExitSystemDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EntryExitSystemController extends BaseController
{
    public function __construct(protected EntryExitSystemService $service)
    {

    }
    public function __invoke(EntryExitSystemRequest $request): JsonResponse
    {

        Log::info("comming request", $request->all());

        $ees = $this->service->ees(
            EntryExitSystemDTO::fromEntryExitSystemDTO($request)
        );

        $additionals = ['online' => $request->online];

        Log::info("ees message", ["ees message" => $ees->message]);
        if (($ees->result['access_allowed'] ?? false) === true) {
            return $this->sendResponse($ees->result, $ees->message, $additionals);
        }

        return $this->sendError($ees->message, $additionals, $ees->result ?? [], 200);

    }
}
