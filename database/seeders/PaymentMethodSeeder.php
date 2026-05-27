<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

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
                ]
            ],
            [
                'slug' => 'card',
                'translations' => [
                    'en' => 'Card',
                    'ru' => 'Карта',
                    'hy' => 'Քարտ',
                ]
            ],
            [
                'slug' => 'transfer',
                'translations' => [
                    'en' => 'Bank Transfer',
                    'ru' => 'Банковский перевод',
                    'hy' => 'Բանկային փոխանցում',
                ]
            ],
            [
                'slug' => 'free',
                'translations' => [
                    'en' => 'Free of charge',
                    'ru' => 'Бесплатно',
                    'hy' => 'Անվճար',
                ]
            ],
        ];

        foreach ($methods as $methodData) {
            $method = PaymentMethod::create([
                'slug' => $methodData['slug'],
            ]);

            foreach ($methodData['translations'] as $locale => $name) {
                $method->translations()->create([
                    'locale' => $locale,
                    'name' => $name,
                ]);
            }
        }
    }
}
