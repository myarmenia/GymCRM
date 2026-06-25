<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->index();
            $table->string('entry_code')->nullable()->index();
            $table->enum('owner_type', ['user', 'person'])->nullable()->index();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->enum('action', ['entry', 'exit', 'unknown'])->default('unknown');
            $table->enum('status', ['success', 'denied'])->default('denied');
            $table->string('reason')->nullable()->index();
            $table->boolean('access_allowed')->default(false);
            $table->string('mac')->nullable()->index();
            $table->timestamp('device_time')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_reports');
    }
};
