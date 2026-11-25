<?php

namespace App\Console\Commands;

use App\Services\MembershipSales\ContactNotePhone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckPersonPhoneNormalization extends Command
{
    protected $signature = 'people:check-phones';

    protected $description = 'Report invalid, missing and colliding customer phone IDs without changing data.';

    public function handle(): int
    {
        $seen = [];
        $invalid = [];
        $missing = [];
        $collisions = [];
        $changes = 0;
        DB::table('people')->orderBy('id')->chunkById(500, function ($people) use (&$seen, &$invalid, &$missing, &$collisions, &$changes): void {
            foreach ($people as $person) {
                if ($person->phone === null || trim($person->phone) === '') {
                    $missing[] = $person->id;

                    continue;
                }
                $canonical = ContactNotePhone::canonical($person->phone);
                if (! ContactNotePhone::isValidPersonPhone($person->phone)) {
                    $invalid[] = $person->id;

                    continue;
                }
                if (isset($seen[$canonical])) {
                    $collisions[] = [$seen[$canonical], $person->id];
                }
                $seen[$canonical] = $person->id;
                if ($canonical !== $person->phone) {
                    $changes++;
                }
            }
        });
        $this->line('Formatting changes: '.$changes);
        $this->line('Missing phone IDs: '.implode(', ', $missing));
        $this->line('Invalid phone IDs: '.implode(', ', $invalid));
        foreach ($collisions as $pair) {
            $this->error('Normalized collision on IDs: '.implode(', ', $pair));
        }

        return $invalid !== [] || $collisions !== [] ? self::FAILURE : self::SUCCESS;
    }
}
