<?php

namespace App\Services\Notifications;

use App\Models\Person;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirebasePushNotificationService
{
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * Send an FCM notification to one device token.
     *
     * @return array{success: bool, reason: string, status_code?: int, message_id?: string, token_should_be_removed?: bool}
     */
    public function sendToToken(
        string $token,
        ?string $title = null,
        ?string $body = null,
        array $data = [],
    ): array {
        $token = trim($token);

        if ($token === '') {
            return $this->failure('missing_token');
        }

        if ($title === null && $body === null && $data === []) {
            return $this->failure('empty_message');
        }

        $credentialsPath = (string) config('services.firebase.credentials');
        $projectId = trim((string) config('services.firebase.project_id'));

        if ($credentialsPath === '' || !is_file($credentialsPath)) {
            return $this->failure('credentials_not_configured');
        }

        if ($projectId === '') {
            return $this->failure('project_not_configured');
        }

        try {
            $credentials = new ServiceAccountCredentials(self::FCM_SCOPE, $credentialsPath);
            $accessToken = $credentials->fetchAuthToken()['access_token'] ?? null;

            if (!is_string($accessToken) || $accessToken === '') {
                return $this->failure('authentication_failed');
            }

            $response = (new Client([
                'connect_timeout' => 10,
                'timeout' => 20,
                'http_errors' => false,
            ]))->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'message' => $this->message($token, $title, $body, $data),
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $payload = json_decode((string) $response->getBody(), true) ?: [];

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'success' => true,
                    'reason' => 'sent',
                    'status_code' => $statusCode,
                    'message_id' => (string) ($payload['name'] ?? ''),
                ];
            }

            $reason = $this->failureReason($payload);
            $result = $this->failure($reason, $statusCode);

            if ($reason === 'invalid_token') {
                $result['token_should_be_removed'] = true;
            }

            $this->logFailure($token, $reason, $statusCode);

            return $result;
        } catch (GuzzleException) {
            $this->logFailure($token, 'transport_error');

            return $this->failure('transport_error');
        } catch (Throwable) {
            $this->logFailure($token, 'send_failed');

            return $this->failure('send_failed');
        }
    }

    /**
     * Send an FCM notification only to the supplied mobile person.
     *
     * @return array{success: bool, reason: string, status_code?: int, message_id?: string, token_should_be_removed?: bool}
     */
    public function sendToPerson(
        Person $person,
        ?string $title = null,
        ?string $body = null,
        array $data = [],
    ): array {
        return $this->sendToToken((string) $person->fcm_token, $title, $body, $data);
    }

    private function message(string $token, ?string $title, ?string $body, array $data): array
    {
        $message = ['token' => $token];

        $notification = array_filter([
            'title' => $title,
            'body' => $body,
        ], fn (?string $value) => $value !== null);

        if ($notification !== []) {
            $message['notification'] = $notification;
        }

        if ($data !== []) {
            $message['data'] = $this->stringifyData($data);
        }

        return $message;
    }

    private function stringifyData(array $data): array
    {
        return collect($data)
            ->mapWithKeys(function (mixed $value, mixed $key): array {
                $value = is_scalar($value) || $value === null
                    ? (string) $value
                    : (string) json_encode($value);

                return [(string) $key => $value];
            })
            ->all();
    }

    private function failureReason(array $payload): string
    {
        $status = (string) data_get($payload, 'error.status', '');
        $message = strtolower((string) data_get($payload, 'error.message', ''));

        if ($status === 'UNREGISTERED'
            || str_contains($message, 'not registered')
            || str_contains($message, 'registration token is not a valid')) {
            return 'invalid_token';
        }

        return $status === 'UNAUTHENTICATED' ? 'authentication_failed' : 'fcm_rejected';
    }

    private function failure(string $reason, ?int $statusCode = null): array
    {
        return array_filter([
            'success' => false,
            'reason' => $reason,
            'status_code' => $statusCode,
        ], fn (mixed $value) => $value !== null);
    }

    private function logFailure(string $token, string $reason, ?int $statusCode = null): void
    {
        Log::warning('Firebase mobile push delivery failed.', array_filter([
            'reason' => $reason,
            'status_code' => $statusCode,
            'token_hint' => $this->tokenHint($token),
        ], fn (mixed $value) => $value !== null));
    }

    private function tokenHint(string $token): string
    {
        return '…'.substr($token, -6);
    }
}
