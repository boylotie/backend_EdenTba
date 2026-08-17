<?php

namespace App\Modules\Content\Events;

use App\Modules\Content\Models\Content;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ContentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Content $content,
    ) {}

    public function broadcastOn(): array
    {
        return ['admin.contents'];
    }

    public function broadcastAs(): string
    {
        return 'ContentCreated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->content->id,
            'title' => $this->content->title,
            'speaker' => $this->content->speakerRel?->name,
        ];
    }
}
