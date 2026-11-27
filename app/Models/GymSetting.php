<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymSetting extends Model
{
    use HasUuidAndVersion;

    public const AUTO_UP_TO_TWO = 'auto_up_to_two';
    public const MANAGER_SELECT_MULTIPLE = 'manager_select_multiple';
    public const MODES = [self::AUTO_UP_TO_TWO, self::MANAGER_SELECT_MULTIPLE];
    public const SALE_DATE = 'sale_date';
    public const FIRST_ENTRY = 'first_entry';
    public const START_MODES = [self::SALE_DATE, self::FIRST_ENTRY];

    protected $guarded = [];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public static function automaticMembershipLimitForGym(int $gymId): int
    {
        return self::query()->where('gym_id', $gymId)->value('membership_selection_mode')
            === self::MANAGER_SELECT_MULTIPLE ? 1 : 2;
    }
}
