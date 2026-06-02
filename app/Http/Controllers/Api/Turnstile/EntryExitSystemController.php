<?php

namespace App\Http\Controllers\Api\Turnstile;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\EntryExitSystemRequest;
use App\Services\Turnstile\EntryExitSystemService;
use App\DTO\Turnstile\EntryExitSystemDTO;
use Illuminate\Http\JsonResponse;

class EntryExitSystemController extends BaseController
{
    public function __construct(protected EntryExitSystemService $service)
    {

    }
    public function __invoke(EntryExitSystemRequest $request): JsonResponse
    {

        \Log::info("comming request", $request->all());

        $ees = $this->service->ees(
            EntryExitSystemDTO::fromEntryExitSystemDTO($request)
        );

        $additionals = ['online' => $request->online];

        \Log::info("ees message", ["ees message" => $ees->message]);
        return $ees->result != null ? $this->sendResponse(null, $ees->message, $additionals) : $this->sendError($ees->message, $additionals);

    }
}
