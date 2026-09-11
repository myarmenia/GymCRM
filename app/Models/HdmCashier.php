<?php

namespace App\Models;

use App\Traits\BelongsToGym;
use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class HdmCashier extends Model
{
    use BelongsToGym;
    use HasUuidAndVersion;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'session_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'status' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function config()
    {
        return $this->belongsTo(HdmConfig::class, 'hdm_config_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isSessionValid(): bool
    {
        if (! $this->session_key) {
            return false;
        }

        if (! $this->session_expires_at) {
            return true;
        }

        return now()->lessThan($this->session_expires_at);
    }
}
