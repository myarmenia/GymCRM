<?php

namespace App\Interfaces\Hdm;

use App\Interfaces\BaseInterface;

interface HdmOperationInterface extends BaseInterface
{
    public function createWithPayments(array $operationData, array $payments = []);
}
