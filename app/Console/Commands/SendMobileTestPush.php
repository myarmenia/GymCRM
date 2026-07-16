<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\Notifications\FirebasePushNotificationService;
use Illuminate\Console\Command;

class SendMobileTestPush extends Command
{
    protected $signature = 'mobile:send-test-push
                            {personId : The target Person ID}
                            {--title=FitTracker test : Notification title}
                            {--body=GymCRM can send mobile push notifications. : Notification body}';

    protected $description = 'Send a test Firebase push notification to one Person device.';

    public function handle(FirebasePushNotificationService $pushNotifications): int
    {
        $person = Person::find($this->argument('personId'));

        if (!$person) {
            $this->error('Person not found.');

            return self::FAILURE;
        }

        if (blank($person->fcm_token)) {
            $this->error('This Person has no registered FCM device token.');

            return self::FAILURE;
        }

        $result = $pushNotifications->sendToPerson(
            $person,
            $this->option('title') ?: null,
            $this->option('body') ?: null,
            ['source' => 'gymcrm_test'],
        );

        if (!$result['success']) {
            $this->error("Push was not sent: {$result['reason']}.");

            return self::FAILURE;
        }

        $this->info('Push sent successfully.');

        return self::SUCCESS;
    }
}
