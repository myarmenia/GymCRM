<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicatePhones = DB::table('people')
            ->select('phone')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        foreach ($duplicatePhones as $phone) {
            $people = DB::table('people')
                ->where('phone', $phone)
                ->orderBy('id')
                ->get(['id', 'phone']);

            foreach ($people->skip(1) as $person) {
                DB::table('people')
                    ->where('id', $person->id)
                    ->update([
                        'phone' => substr($person->phone . '-duplicate-' . $person->id, 0, 50),
                    ]);
            }
        }

        Schema::table('people', function (Blueprint $table) {
            $table->unique('phone', 'people_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropUnique('people_phone_unique');
        });
    }
};
