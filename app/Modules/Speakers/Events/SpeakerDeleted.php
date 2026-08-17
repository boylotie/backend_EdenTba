<?php

namespace App\Modules\Speakers\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SpeakerDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $speakerId,
        public readonly string $name,
    ) {}

    public function broadcastOn(): array
    {
        return ['admin.speakers'];
    }

    public function broadcastAs(): string
    {
        return 'SpeakerDeleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->speakerId,
            'name' => $this->name,
        ];
    }
}
