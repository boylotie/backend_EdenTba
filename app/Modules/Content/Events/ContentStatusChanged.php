<?php

namespace App\Modules\Content\Events;

use App\Modules\Content\Models\Content;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ContentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Content $content,
        public readonly string $from,
        public readonly string $to,
    ) {}

    public function broadcastOn(): array
    {
        return ['admin.contents'];
    }

    public function broadcastAs(): string
    {
        return 'ContentStatusChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->content->id,
            'title' => $this->content->title,
            'from' => $this->from,
            'to' => $this->to,
            'speaker' => $this->content->speakerRel?->name,
        ];
    }
}
