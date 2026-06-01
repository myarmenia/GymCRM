<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryPermission extends Model
{
    //
    protected $guarded=[];
    protected $table = "entry_permissions";

    public function entryCode()
    {
        return $this->belongsTo(EntryCode::class);
    }

    public function relation()
    {
        return $this->morphTo();
    }

}
