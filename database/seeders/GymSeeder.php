<?php

namespace Database\Seeders;

use App\Models\Gym;
use Illuminate\Database\Seeder;

class GymSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Gym::create([
            'name' => 'Default Gym',
            'address' => 'Yerevan',
            'phone' => '+374000000',
            'email' => 'gym@example.com',
        ]);
    }
}
