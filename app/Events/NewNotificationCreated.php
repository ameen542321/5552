<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class NewNotificationCreated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public Notification $notification;
    public int $userId;

    public function __construct(Notification $notification, int $userId)
    {
        $this->notification = $notification;
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.'.$this->userId);
    }

    public function broadcastAs()
    {
        return 'new-notification';
    }

    public function broadcastWith()
    {
        return [
            'id'      => $this->notification->id,
            'title'   => $this->notification->title,
            'message' => $this->notification->message,
        ];
    }
}
