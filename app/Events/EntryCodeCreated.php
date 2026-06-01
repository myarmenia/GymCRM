<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EntryCodeCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $client_id;
    public $token;

    /**
     * Create a new event instance.
     */
    public function __construct($client_id, $token)
    {
        $this->client_id = $client_id;
        $this->token = $token;

        Log::info("EntryCodeCreated Event triggered", [
            'client_id' => $client_id,
            'token' => $token,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('adminEntryCodes.' . $this->client_id)
        ];
    }
    public function broadcastWith()
    {
        return [
            'client_id' => $this->client_id,
            'token' => $this->token,
        ];
    }
}
