<?php

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\Lang;

use Illuminate\Database\Seeder;

class GymSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gym = Gym::create([
            'name' => 'Default Gym',
            'address' => 'Yerevan',
            'phone' => '+374000000',
            'email' => 'gym@example.com',
        ]);

        $hyLang = Lang::where('code', 'hy')->first();

        if ($hyLang) {
            $gym->languages()->attach($hyLang->id, [
                'active' => true,
            ]);
        }
    }
}
