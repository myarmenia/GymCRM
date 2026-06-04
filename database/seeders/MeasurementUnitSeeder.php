<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MeasurementUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('measurement_units')->insert([
            [
                'code' => 'pcs',
                'name' => 'Հատով',
                'type' => 'quantity',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'g',
                'name' => 'Գրամ',
                'type' => 'weight',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ml',
                'name' => 'Միլլիլիտր',
                'type' => 'volume',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'cm',
                'name' => 'Սանտիմետր',
                'type' => 'length',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'box',
                'name' => 'Տուփով',
                'type' => 'package',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'pack',
                'name' => 'Փաթեթով',
                'type' => 'package',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'btl',
                'name' => 'Շշով',
                'type' => 'package',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}