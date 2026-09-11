<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class EntryPermission extends Model
{
    use HasUuidAndVersion;

    //
    protected $guarded = [];

    protected $table = 'entry_permissions';

    public function entryCode()
    {
        return $this->belongsTo(EntryCode::class);
    }

    public function relation()
    {
        return $this->morphTo();
    }
}
