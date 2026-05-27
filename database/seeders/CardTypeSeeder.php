<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CardType;

class CardTypeSeeder extends Seeder
{
    public function run(): void
    {
        $cards = [
            'Visa',
            'MasterCard',
            'American Express',
            'Diners Club',
            'Arca',
            'Mir',
        ];

        foreach ($cards as $card) {
            CardType::create([
                'name' => $card
            ]);
        }
    }
}
