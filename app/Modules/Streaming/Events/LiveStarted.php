<?php

namespace App\Modules\Streaming\Events;

use App\Modules\Streaming\Models\LiveSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LiveStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LiveSession $session,
    ) {
        \Log::info('[Reverb Backend] LiveStarted event created', [
            'session_id' => $session->id,
            'channel' => 'live.status',
            'event' => 'LiveStarted',
        ]);
    }

    public function broadcastOn(): Channel
    {
        return new Channel('live.status');
    }

    public function broadcastAs(): string
    {
        return 'LiveStarted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'started_at' => $this->session->started_at?->toISOString(),
        ];
    }
}
