<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('status')
                ->constrained('payment_methods')
                ->restrictOnDelete();
        });

        $paymentMethodIds = DB::table('payment_methods')
            ->whereIn('slug', ['cash', 'card', 'transfer'])
            ->pluck('id', 'slug');

        foreach ([
            'cash' => 'cash',
            'card' => 'card',
            'online' => 'transfer',
        ] as $legacyMethod => $paymentMethodSlug) {
            $paymentMethodId = $paymentMethodIds->get($paymentMethodSlug);

            if (! $paymentMethodId && DB::table('purchases')->where('payment_method', $legacyMethod)->exists()) {
                throw new RuntimeException(
                    "Cannot migrate purchases using [{$legacyMethod}]: payment method [{$paymentMethodSlug}] is missing."
                );
            }

            if ($paymentMethodId) {
                DB::table('purchases')
                    ->where('payment_method', $legacyMethod)
                    ->update(['payment_method_id' => $paymentMethodId]);
            }
        }

        if (DB::table('purchases')->whereNull('payment_method_id')->exists()) {
            throw new RuntimeException('Cannot migrate purchases: one or more legacy payment methods are unsupported.');
        }

        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_method_id')->nullable(false)->change();
            $table->dropColumn('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'card', 'online'])
                ->nullable()
                ->after('status');
        });

        $paymentMethodSlugs = DB::table('payment_methods')->pluck('slug', 'id');

        DB::table('purchases')
            ->select(['id', 'payment_method_id'])
            ->orderBy('id')
            ->eachById(function ($purchases) use ($paymentMethodSlugs) {
                foreach ($purchases as $purchase) {
                    $slug = $paymentMethodSlugs->get($purchase->payment_method_id);
                    $legacyMethod = $slug === 'transfer' ? 'online' : $slug;

                    if (! in_array($legacyMethod, ['cash', 'card', 'online'], true)) {
                        throw new RuntimeException(
                            "Cannot roll back purchase [{$purchase->id}]: payment method [{$slug}] is unsupported."
                        );
                    }

                    DB::table('purchases')
                        ->where('id', $purchase->id)
                        ->update(['payment_method' => $legacyMethod]);
                }
            });

        Schema::table('purchases', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'card', 'online'])
                ->nullable(false)
                ->change();
            $table->dropConstrainedForeignId('payment_method_id');
        });
    }
};
