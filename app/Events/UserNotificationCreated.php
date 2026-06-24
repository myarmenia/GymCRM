<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Notification $notification,
        public int $unreadCount,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->notification->recipient_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.notification.created';
    }

    public function broadcastWith(): array
    {
        $this->notification->loadMissing(['sender', 'about']);

        return [
            'notification' => [
                'id' => $this->notification->id,
                'sender_id' => $this->notification->sender_id,
                'recipient_id' => $this->notification->recipient_id,
                'about_id' => $this->notification->about_id,
                'title' => $this->notification->title,
                'description' => $this->notification->description,
                'seen' => (bool) $this->notification->seen,
                'created_at' => $this->notification->created_at?->toDateTimeString(),
                'sender' => $this->notification->sender ? [
                    'id' => $this->notification->sender->id,
                    'name' => $this->notification->sender->name,
                    'surname' => $this->notification->sender->surname,
                    'email' => $this->notification->sender->email,
                ] : null,
                'about' => $this->notification->about ? [
                    'id' => $this->notification->about->id,
                    'name' => $this->notification->about->name,
                    'surname' => $this->notification->about->surname,
                    'phone' => $this->notification->about->phone,
                ] : null,
                'was_unread' => true,
            ],
            'unread_count' => $this->unreadCount,
        ];
    }
}
