<?php

namespace App\Models;

use App\Traits\BelongsToGym;
use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use BelongsToGym;
    use HasUuidAndVersion;

    protected $guarded = [];

    protected $appends = ['file_url'];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
