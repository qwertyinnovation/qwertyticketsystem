<?php

namespace App\Notifications;

use App\Models\ServiceTicket;
use App\Models\User;
use App\Notifications\Channels\EmailJsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ServiceTicketChangedNotification extends Notification
{
    use Queueable;

    public const TYPE_CREATED = 'created';

    public const TYPE_RESPONDED = 'responded';

    public const TYPE_DELETED = 'deleted';

    /**
     * @param  array{
     *     id: int,
     *     title: string,
     *     project_name: string,
     *     status: string,
     *     requester_role_label: string,
     *     submitted_by_name: string,
     *     public_tracking_token: string|null
     * }  $ticket
     */
    public function __construct(
        private readonly array $ticket,
        private readonly string $changeType,
        private readonly ?string $actorName = null,
        private readonly ?string $previousStatus = null,
        private readonly ?string $newStatus = null,
        private readonly ?string $message = null,
    ) {
    }

    public static function fromTicket(
        ServiceTicket $ticket,
        string $changeType,
        ?User $actor = null,
        ?string $previousStatus = null,
        ?string $newStatus = null,
        ?string $message = null,
    ): self {
        $ticket->loadMissing(['project', 'submittedBy']);
        $requesterRoles = ServiceTicket::requesterRoles();

        return new self(
            [
                'id' => (int) $ticket->id,
                'title' => self::displayText($ticket->title, 'Untitled ticket'),
                'project_name' => self::displayText($ticket->project?->name, 'Unknown Project'),
                'status' => self::displayText($newStatus ?: $ticket->status, 'Unknown'),
                'requester_role_label' => $requesterRoles[$ticket->requester_role] ?? ucfirst((string) $ticket->requester_role),
                'submitted_by_name' => self::displayText($ticket->submittedBy?->name, 'Public submission'),
                'public_tracking_token' => is_string($ticket->public_tracking_token) && trim($ticket->public_tracking_token) !== ''
                    ? $ticket->public_tracking_token
                    : null,
            ],
            $changeType,
            $actor?->name,
            $previousStatus,
            $newStatus,
            $message,
        );
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [EmailJsChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toEmailJs(object $notifiable): array
    {
        $toEmail = $notifiable instanceof User ? trim((string) $notifiable->email) : '';
        $actionUrl = $this->ticketUrl($notifiable);

        if ($toEmail === '') {
            throw new \RuntimeException('Recipient email is missing.');
        }

        return [
            'to_email' => $toEmail,
            'from_email' => (string) config('mail.from.address', ''),
            'from_name' => (string) config('mail.from.name', ''),
            'subject' => $this->subjectLine(),
            'message' => $this->messageBody($actionUrl),
            'ticket_id' => (string) $this->ticket['id'],
            'ticket_title' => $this->ticket['title'],
            'project_name' => $this->ticket['project_name'],
            'status' => $this->ticket['status'],
            'requester_role' => $this->ticket['requester_role_label'],
            'submitted_by' => $this->ticket['submitted_by_name'],
            'change_type' => $this->changeType,
            'action_url' => $actionUrl,
            'changed_by' => $this->actorName ?? '',
        ];
    }

    public function changeType(): string
    {
        return $this->changeType;
    }

    public function ticketId(): int
    {
        return $this->ticket['id'];
    }

    private function subjectLine(): string
    {
        return match ($this->changeType) {
            self::TYPE_CREATED => 'New service ticket #'.$this->ticket['id'].': '.$this->ticket['title'],
            self::TYPE_RESPONDED => 'Service ticket #'.$this->ticket['id'].' updated: '.$this->ticket['title'],
            self::TYPE_DELETED => 'Service ticket #'.$this->ticket['id'].' deleted: '.$this->ticket['title'],
            default => 'Service ticket #'.$this->ticket['id'].' changed: '.$this->ticket['title'],
        };
    }

    private function summaryLine(): string
    {
        return match ($this->changeType) {
            self::TYPE_CREATED => 'A new service ticket was submitted.',
            self::TYPE_RESPONDED => 'A response was added to a service ticket.',
            self::TYPE_DELETED => 'A service ticket was deleted.',
            default => 'A service ticket was changed.',
        };
    }

    private function messageBody(string $actionUrl): string
    {
        $lines = [
            $this->summaryLine(),
            'Project: '.$this->ticket['project_name'],
            'Requester: '.$this->ticket['requester_role_label'].' ('.$this->ticket['submitted_by_name'].')',
            'Status: '.$this->ticket['status'],
        ];

        if ($this->actorName !== null && trim($this->actorName) !== '') {
            $lines[] = 'Changed by: '.$this->actorName;
        }

        if ($this->statusChanged()) {
            $lines[] = 'Status changed: '.$this->previousStatus.' -> '.$this->newStatus;
        }

        if ($this->message !== null && trim($this->message) !== '') {
            $lines[] = 'Message: '.Str::limit(trim($this->message), 500);
        }

        if ($this->changeType === self::TYPE_DELETED) {
            $lines[] = 'This ticket has been removed from the system.';
        } else {
            $lines[] = 'Open Ticket: '.$actionUrl;
        }

        return implode("\n", $lines);
    }

    private function ticketUrl(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->hasPermission(User::PERMISSION_MANAGE_TICKETS)) {
            return route('service-tickets.show', $this->ticket['id']);
        }

        if ($this->ticket['public_tracking_token'] !== null) {
            return route('service-tickets.public.track', $this->ticket['public_tracking_token']);
        }

        return route('service-tickets.index');
    }

    private function statusChanged(): bool
    {
        return $this->previousStatus !== null
            && $this->newStatus !== null
            && $this->previousStatus !== $this->newStatus;
    }

    private static function displayText(?string $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : $fallback;
    }
}
