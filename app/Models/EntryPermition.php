<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryPermition extends Model
{
    //
    protected $guarded=[];
    protected $table = "entry_permitions";

    public function entryCode()
    {
        return $this->belongsTo(EntryCode::class);
    }

    public function relation()
    {
        return $this->morphTo();
    }

}
