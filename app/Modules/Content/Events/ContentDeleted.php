<?php

namespace App\Modules\Content\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ContentDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $contentId,
        public readonly string $title,
    ) {}

    public function broadcastOn(): array
    {
        return ['admin.contents'];
    }

    public function broadcastAs(): string
    {
        return 'ContentDeleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->contentId,
            'title' => $this->title,
        ];
    }
}
