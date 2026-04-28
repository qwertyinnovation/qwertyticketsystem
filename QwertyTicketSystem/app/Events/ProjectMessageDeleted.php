<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $projectId,
        public int $messageId,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('projects.'.$this->projectId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'project.message.deleted';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return [
            'project_id' => $this->projectId,
            'id' => $this->messageId,
        ];
    }
}
