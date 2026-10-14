<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_contract_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->unique()->constrained()->restrictOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('legal_name')->nullable();
            $table->string('director_name')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('legal_address', 500)->nullable();
            $table->string('service_address', 500)->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->text('bank_details')->nullable();
            $table->json('clauses');
            $table->json('rules');
            $table->unsignedInteger('template_version')->default(1);
            $table->timestamps();
        });

        Schema::create('sale_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('gym_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_sale_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('paired_black_revision')->nullable();
            $table->string('status', 20)->default('pending');
            $table->decimal('price', 10, 2);
            $table->date('issued_on')->nullable();
            $table->string('gym_legal_name')->nullable();
            $table->string('gym_director_name')->nullable();
            $table->string('gym_tax_number')->nullable();
            $table->string('gym_registration_number')->nullable();
            $table->string('gym_legal_address', 500)->nullable();
            $table->string('gym_service_address', 500)->nullable();
            $table->string('gym_city')->nullable();
            $table->string('gym_phone')->nullable();
            $table->text('gym_bank_details')->nullable();
            $table->string('client_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_address', 500)->nullable();
            $table->date('client_birth_date')->nullable();
            $table->string('client_passport_series', 100)->nullable();
            $table->string('client_passport_issued_by')->nullable();
            $table->date('client_passport_issued_at')->nullable();
            $table->string('membership_name')->nullable();
            $table->date('membership_start_date')->nullable();
            $table->date('membership_end_date')->nullable();
            $table->unsignedInteger('membership_visits_limit')->nullable();
            $table->unsignedInteger('template_version')->nullable();
            $table->json('snapshot')->nullable();
            $table->string('pdf_disk', 30)->nullable();
            $table->string('pdf_path', 600)->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->unique(['gym_id', 'number', 'revision']);
            $table->unique(['membership_sale_id', 'revision']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_contracts');
        Schema::dropIfExists('sale_contract_settings');
    }
};
