<?php

namespace App\Modules\Notifications\Events;

use App\Modules\Notifications\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Notification $notification,
    ) {
        \Log::info('[Reverb Backend] NotificationCreated event created', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'channel' => 'user.'.$notification->user_id,
            'event' => 'NotificationCreated',
        ]);
    }

    public function broadcastOn(): array
    {
        return [new Channel('user.'.$this->notification->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'entity_type' => $this->notification->entity_type,
            'entity_id' => $this->notification->entity_id,
            'read_at' => $this->notification->read_at?->toISOString(),
        ];
    }
}
