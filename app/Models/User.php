<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\BelongsToGym;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes, BelongsToGym;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    // protected $fillable = [
    //     'name',
    //     'email',
    //     'password',
    // ];

    protected $guarded = [];


    public function documents()
    {
        return $this->morphMany(Document::class, 'owner');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function entryPermissions()
    {
        return $this->morphMany(EntryPermission::class, 'relation');

    }


    // Օժանդակ մեթոդ՝ ստուգելու, արդյոք user-ն ունի կոնկրետ entry code
    public function hasEntryCode(EntryCode $entryCode): bool
    {
        return $this->entryPermissions()
            ->where('entry_code_id', $entryCode->id)
            ->where('status', 1)
            ->exists();
    }

    public function faceId()
    {
        return $this->entryPermissions()
            ->whereHas('entryCode', function ($q) {
                $q->where('type', 'face_id');
            });
    }


    public function rfIds()
    {
        return $this->entryPermissions()
            ->whereHas('entryCode', function ($q) {
                $q->where('type', 'rfid');
            });
    }

    public function attendanceSheets()
    {
        return $this->morphMany(AttendanceSheet::class, 'relation');
    }


    public function getEntryCodesAttribute()
    {
        return $this->entryPermissions()
            ->where('status', 1)
            ->with('entryCode')
            ->get()
            ->pluck('entryCode')
            ->filter();
    }

}
