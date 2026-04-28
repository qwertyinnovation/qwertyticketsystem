<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GeneralMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $messageId) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('general-chat'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'general.message.deleted';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
        ];
    }
}
