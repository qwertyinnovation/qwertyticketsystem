<?php

namespace App\Events;

use App\Models\GeneralChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class GeneralMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GeneralChatMessage $generalChatMessage) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('general-chat'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'general.message.created';
    }

    /**
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        $message = $this->generalChatMessage->loadMissing('author:id,name,role');
        $author = $message->author;
        $role = (string) ($author?->role ?? '');

        return [
            'id' => (int) $message->id,
            'user_id' => (int) ($message->user_id ?? 0),
            'message' => (string) $message->message,
            'created_at' => $message->created_at?->toIso8601String(),
            'created_at_label' => $message->created_at?->format('M d, H:i'),
            'author_name' => $author?->name ?? 'Unknown User',
            'author_role' => $role,
            'author_role_label' => User::roles()[$role] ?? Str::of($role)->replace('_', ' ')->title()->value(),
        ];
    }
}
