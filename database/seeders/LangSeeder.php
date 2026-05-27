<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lang;

class LangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $langs = [
            ['id' => 1, 'name' => 'hy'],
            ['id' => 2, 'name' => 'ru'],
            ['id' => 3, 'name' => 'en'],
        ];

        foreach ($langs as $lang) {
            Lang::updateOrCreate(['name' => $lang['name']], $lang);
        }
    }
}
