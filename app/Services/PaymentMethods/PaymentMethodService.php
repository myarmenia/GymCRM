<?php

namespace App\Services\PaymentMethods;

use App\Interfaces\PaymentMethods\PaymentMethodInterface;

class PaymentMethodService
{

    public function __construct(protected PaymentMethodInterface $paymentMethodRepository)
    {
    }

    public function geyPayMethodsWithCardTypes(){

        return $this->paymentMethodRepository->getAll(['translations','cardTypes']);
    }


}
