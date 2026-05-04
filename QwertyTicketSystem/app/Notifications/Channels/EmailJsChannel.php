<?php

namespace App\Notifications\Channels;

use App\Services\EmailJsService;
use Illuminate\Notifications\Notification;
use RuntimeException;

class EmailJsChannel
{
    public function __construct(private readonly EmailJsService $emailJs)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toEmailJs')) {
            throw new RuntimeException('Notification is missing toEmailJs() payload method.');
        }

        $payload = $notification->toEmailJs($notifiable);

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid EmailJS payload.');
        }

        $this->emailJs->send($payload);
    }
}

