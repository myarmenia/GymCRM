<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_contract_settings', function (Blueprint $table): void {
            $table->string('contract_title')->default('Մարմնամարզական ծառայությունների մատուցման պայմանագիր');
        });

        Schema::table('sale_contracts', function (Blueprint $table): void {
            $table->string('contract_title')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sale_contracts', function (Blueprint $table): void {
            $table->dropColumn('contract_title');
        });

        Schema::table('sale_contract_settings', function (Blueprint $table): void {
            $table->dropColumn('contract_title');
        });
    }
};
