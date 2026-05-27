<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            PermissionTableSeeder::class,
            RoleSeeder::class,
            CreateAdminUserSeeder::class,
            LangSeeder::class,
            CountriesSeeder::class,
            CountryTranslationSeeder::class,
            GymSeeder::class,
            CompanySeeder::class,
            PaymentMethodSeeder::class,
            CardTypeSeeder::class,
            CardTypePaymentMethodSeeder::class,

        ]);
    }
}
