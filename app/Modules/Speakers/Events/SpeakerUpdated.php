<?php

namespace App\Modules\Speakers\Events;

use App\Modules\Speakers\Models\Speaker;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SpeakerUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Speaker $speaker,
    ) {}

    public function broadcastOn(): array
    {
        return ['admin.speakers'];
    }

    public function broadcastAs(): string
    {
        return 'SpeakerUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->speaker->id,
            'name' => $this->speaker->name,
            'title' => $this->speaker->title,
        ];
    }
}
