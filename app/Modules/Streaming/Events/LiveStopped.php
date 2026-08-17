<?php

namespace App\Modules\Streaming\Events;

use App\Modules\Streaming\Models\LiveSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LiveStopped implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LiveSession $session,
    ) {
        \Log::info('[Reverb Backend] LiveStopped event created', [
            'session_id' => $session->id,
            'channel' => 'live.status',
            'event' => 'LiveStopped',
        ]);
    }

    public function broadcastOn(): Channel
    {
        return new Channel('live.status');
    }

    public function broadcastAs(): string
    {
        return 'LiveStopped';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'stopped_at' => now()->toISOString(),
        ];
    }
}
