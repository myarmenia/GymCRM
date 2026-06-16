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
            ['id' => 1, 'code' => 'hy', 'name' => 'Հայերեն'],
            ['id' => 2, 'code' => 'ru', 'name' => 'Русский'],
            ['id' => 3, 'code' => 'en', 'name' => 'English'],
        ];

        foreach ($langs as $lang) {
            Lang::updateOrCreate(['code' => $lang['code']], $lang);
        }
    }
}
