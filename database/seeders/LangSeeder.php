<?php

namespace Database\Seeders;

use App\Models\Lang;
use App\Support\StableUuid;
use Illuminate\Database\Seeder;

class LangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $langs = [
            ['code' => 'hy', 'name' => 'Հայերեն'],
            ['code' => 'ru', 'name' => 'Русский'],
            ['code' => 'en', 'name' => 'English'],
        ];

        foreach ($langs as $lang) {
            $record = Lang::firstOrNew(['code' => $lang['code']]);
            $record->name = $lang['name'];

            if (! $record->exists) {
                $record->fill(StableUuid::seedIdentity('langs', $lang['code']));
            }

            $record->save();
        }
    }
}
