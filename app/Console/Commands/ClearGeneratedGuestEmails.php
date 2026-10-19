<?php

namespace App\Console\Commands;

use App\Models\Person;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearGeneratedGuestEmails extends Command
{
    protected $signature = 'guests:clear-generated-emails {--apply : Clear matching emails after reviewing the dry run}';

    protected $description = 'Find or clear generated guest.local email addresses';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $matched = 0;
        $cleared = 0;

        Person::query()
            ->where('email', 'like', 'guest-%@guest.local')
            ->select(['id', 'email'])
            ->chunkById(200, function ($people) use ($apply, &$matched, &$cleared): void {
                foreach ($people as $person) {
                    if (! self::isGeneratedEmail($person->email)) {
                        continue;
                    }

                    $matched++;

                    if (! $apply) {
                        continue;
                    }

                    DB::transaction(function () use ($person, &$cleared): void {
                        $current = Person::query()->whereKey($person->id)->lockForUpdate()->first();

                        if (! $current || ! self::isGeneratedEmail($current->email)) {
                            return;
                        }

                        $current->email = null;
                        $current->save();
                        $cleared++;
                    });
                }
            });

        $this->info("Matched {$matched} generated guest email(s).");
        $this->info($apply ? "Cleared {$cleared} email(s)." : 'Dry run only. Use --apply to clear them.');

        return self::SUCCESS;
    }

    private static function isGeneratedEmail(?string $email): bool
    {
        return $email !== null && preg_match(
            '/^guest-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}@guest\.local$/i',
            $email,
        ) === 1;
    }
}
