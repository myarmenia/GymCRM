<?php

namespace App\Models;

use App\Traits\BelongsToGym;
use Illuminate\Database\Eloquent\Model;

class HdmCashier extends Model
{
    use BelongsToGym;

    protected $guarded = [];

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
        if (!$this->session_key)
            return false;
        if (!$this->session_expires_at)
            return true;
        return now()->lessThan($this->session_expires_at);
    }
}
