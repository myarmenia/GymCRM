<?php

namespace App\Repositories\Hdm;

use App\Interfaces\Hdm\HdmOperationInterface;
use App\Models\HdmOperation;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

class HdmOperationRepository extends BaseRepository implements HdmOperationInterface
{

    public function __construct(HdmOperation $model)
    {
        parent::__construct($model);
    }

    public function createWithPayments(array $operationData, array $payments = []): HdmOperation
    {
        return DB::transaction(function () use ($operationData, $payments) {
            // 1. Создаем операцию
            $operation = $this->create($operationData);

            // 2. Создаем платежи
            foreach ($payments as $payment) {
                $operation->payments()->create([
                    'payment_method' => $payment['method'] ?? 'cash',
                    'amount' => $payment['amount']
                ]);
            }

            return $operation;
        });
    }
}
