<?php

namespace App\Jobs;

use App\Models\SaleContract;
use App\Services\SaleContracts\SaleContractService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateSaleContractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $contractId) {}

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(SaleContractService $service): void
    {
        $contract = SaleContract::query()->findOrFail($this->contractId);
        $service->render($contract);
    }
}
