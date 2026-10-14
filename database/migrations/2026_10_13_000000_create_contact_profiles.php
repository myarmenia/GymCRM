<?php

use App\Services\MembershipSales\ContactNotePhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('gym_id')->constrained('gyms');
            $table->foreignId('user_id')->constrained('users');
            $table->uuid('group_uuid')->index();
            $table->boolean('is_primary')->default(false);
            $table->string('phone_number', 32);
            $table->string('phone_normalized', 32);
            $table->string('name')->nullable();
            $table->string('surname')->nullable();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('relationship')->nullable();
            $table->timestamps();
            $table->unique(['gym_id', 'phone_normalized']);
            $table->index(['gym_id', 'user_id', 'group_uuid']);
        });

        Schema::table('contact_notes', function (Blueprint $table) {
            $table->foreignId('contact_profile_id')->nullable()->constrained('contact_profiles');
        });

        // A legacy phone becomes a primary profile. The earliest note UUID is
        // stable across databases after the existing note sync has run.
        $profiles = [];
        DB::table('contact_notes')->orderBy('created_at')->orderBy('id')->chunk(500, function ($notes) use (&$profiles): void {
            foreach ($notes as $note) {
                $gymId = DB::table('users')->where('id', $note->user_id)->value('gym_id');
                $normalized = ContactNotePhone::canonical((string) $note->phone_number);
                if ($gymId === null || $normalized === '') {
                    continue;
                }
                $key = $gymId.'|'.$normalized;
                if (! isset($profiles[$key])) {
                    $uuid = (string) $note->uuid;
                    $profiles[$key] = DB::table('contact_profiles')->insertGetId([
                        'uuid' => $uuid,
                        'version' => 1,
                        'gym_id' => $gymId,
                        'user_id' => $note->user_id,
                        'group_uuid' => $uuid,
                        'is_primary' => true,
                        'phone_number' => $note->phone_number,
                        'phone_normalized' => $normalized,
                        'created_at' => $note->created_at,
                        'updated_at' => $note->created_at,
                    ]);
                }
                DB::table('contact_notes')->where('id', $note->id)
                    ->update(['contact_profile_id' => $profiles[$key]]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact_notes', fn (Blueprint $table) => $table->dropConstrainedForeignId('contact_profile_id'));
        Schema::dropIfExists('contact_profiles');
    }
};
