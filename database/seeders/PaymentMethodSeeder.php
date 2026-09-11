<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Support\StableUuid;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'slug' => 'cash',
                'translations' => [
                    'en' => 'Cash',
                    'ru' => 'Наличные',
                    'hy' => 'Կանխիկ',
                ],
            ],
            [
                'slug' => 'card',
                'translations' => [
                    'en' => 'Card',
                    'ru' => 'Карта',
                    'hy' => 'Քարտ',
                ],
            ],
            [
                'slug' => 'transfer',
                'translations' => [
                    'en' => 'Bank Transfer',
                    'ru' => 'Банковский перевод',
                    'hy' => 'Բանկային փոխանցում',
                ],
            ],
            [
                'slug' => 'free',
                'translations' => [
                    'en' => 'Free of charge',
                    'ru' => 'Бесплатно',
                    'hy' => 'Անվճար',
                ],
            ],
        ];

        foreach ($methods as $methodData) {
            $method = PaymentMethod::create([
                'slug' => $methodData['slug'],
                ...StableUuid::seedIdentity('payment-methods', $methodData['slug']),
            ]);

            foreach ($methodData['translations'] as $locale => $name) {
                $method->translations()->create([
                    'locale' => $locale,
                    'name' => $name,
                    ...StableUuid::seedIdentity('payment-method-translations', "{$methodData['slug']}:{$locale}"),
                ]);
            }
        }
    }
}
