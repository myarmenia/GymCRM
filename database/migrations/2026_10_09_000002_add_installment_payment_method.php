<?php

use App\Support\StableUuid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $identity = StableUuid::seedIdentity('payment-methods', 'installment');
        $methodId = DB::table('payment_methods')->where('slug', 'installment')->value('id');

        if (! $methodId) {
            $methodId = DB::table('payment_methods')->insertGetId([
                'slug' => 'installment',
                ...$identity,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            'en' => 'Installment',
            'ru' => 'Рассрочка',
            'hy' => 'Ապառիկ',
        ] as $locale => $name) {
            DB::table('payment_method_translations')->updateOrInsert(
                ['payment_method_id' => $methodId, 'locale' => $locale],
                [
                    'name' => $name,
                    ...StableUuid::seedIdentity('payment-method-translations', "installment:{$locale}"),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        $uuid = StableUuid::seedIdentity('payment-methods', 'installment')['uuid'];
        $methodId = DB::table('payment_methods')->where('uuid', $uuid)->value('id');

        if (! $methodId) {
            return;
        }

        DB::table('payment_method_translations')->where('payment_method_id', $methodId)->delete();
        DB::table('payment_methods')->where('id', $methodId)->delete();
    }
};
