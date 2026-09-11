<?php

namespace Database\Seeders;

use App\Models\CardType;
use App\Support\StableUuid;
use Illuminate\Database\Seeder;

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
                'name' => $card,
                ...StableUuid::seedIdentity('card-types', mb_strtolower($card)),
            ]);
        }
    }
}
