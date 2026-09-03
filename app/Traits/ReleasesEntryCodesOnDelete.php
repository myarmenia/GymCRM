<?php

namespace App\Traits;

use App\Models\EntryCode;
use App\Models\EntryPermission;
use Illuminate\Database\Eloquent\Model;

trait ReleasesEntryCodesOnDelete
{
    protected static function bootReleasesEntryCodesOnDelete(): void
    {
        static::deleting(function (Model $owner): void {
            if (method_exists($owner, 'isForceDeleting') && $owner->isForceDeleting()) {
                return;
            }

            $connection = $owner->getConnectionName() ?? (string) config('database.default');
            $entryCodeIds = $owner->entryPermissions()
                ->whereNull('deleted_at')
                ->pluck('entry_code_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $owner->entryPermissions()->delete();

            foreach ($entryCodeIds as $entryCodeId) {
                $stillUsed = EntryPermission::on($connection)
                    ->where('entry_code_id', $entryCodeId)
                    ->where('status', true)
                    ->whereNull('deleted_at')
                    ->exists();
                if (! $stillUsed) {
                    EntryCode::on($connection)->whereKey($entryCodeId)->update(['activation' => false]);
                }
            }
        });
    }
}
