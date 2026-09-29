<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use App\Support\StableUuid;
use Illuminate\Database\Seeder;

class MeasurementUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            [
                'code' => 'pcs',
                'name' => 'Piece',
                'type' => 'quantity',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'g',
                'name' => 'Gram',
                'type' => 'weight',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ml',
                'name' => 'Milliliter',
                'type' => 'volume',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'cm',
                'name' => 'Centimeter',
                'type' => 'length',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'box',
                'name' => 'Box',
                'type' => 'package',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'btl',
                'name' => 'Bottle',
                'type' => 'package',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($units as $unit) {
            MeasurementUnit::query()->updateOrCreate(
                ['code' => $unit['code']],
                [
                    ...$unit,
                    ...StableUuid::seedIdentity('measurement-units', $unit['code']),
                ],
            );
        }
    }
}
