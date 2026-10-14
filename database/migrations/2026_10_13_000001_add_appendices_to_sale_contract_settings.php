<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_contract_settings', function (Blueprint $table): void {
            $table->string('rules_title')->default('Ակումբի ներքին կանոնակարգ');
            $table->json('appendices')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sale_contract_settings', function (Blueprint $table): void {
            $table->dropColumn(['rules_title', 'appendices']);
        });
    }
};
