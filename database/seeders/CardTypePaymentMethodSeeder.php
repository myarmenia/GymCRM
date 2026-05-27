<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;
use App\Models\CardType;

class CardTypePaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $cardMethod = PaymentMethod::where('slug', 'card')->firstOrFail();
        $cardTypes = CardType::pluck('id');

        $cardMethod->cardTypes()->sync($cardTypes);
    }
}
