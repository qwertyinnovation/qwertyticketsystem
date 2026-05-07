<?php

namespace App\Notifications;

use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subjectLine())
            ->line(new HtmlString('<p style="margin:0 0 12px;"><img src="'.$this->logoUrl().'" alt="Qwerty Innovation" style="max-width:150px;height:auto;" /></p>'))
            ->greeting('Dear Team,')
            ->line($this->summaryLine())
            ->line('Ticket ID: #'.$this->ticket['id'])
            ->line('Title: '.$this->ticket['title'])
            ->line('Project: '.$this->ticket['project_name'])
            ->line('Requester: '.$this->requesterDisplay())
            ->line('Current Status: '.$this->ticket['status']);

        if ($this->actorName !== null && trim($this->actorName) !== '') {
            $mail->line('Updated By: '.$this->actorName);
        }

        if ($this->statusChanged()) {
            $mail->line('Status Change: '.$this->previousStatus.' -> '.$this->newStatus);
        }

        if ($this->message !== null && trim($this->message) !== '') {
            $mail->line('Latest Update: '.Str::limit(trim($this->message), 500));
        }

        if ($this->changeType === self::TYPE_DELETED) {
            return $mail
                ->line('This service ticket has been removed from the system.')
                ->salutation('Regards, Qwerty Service Team');
        }

        return $mail
            ->action('Open Ticket', $this->ticketUrl($notifiable))
            ->salutation('Regards, Qwerty Service Team');
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

    private function requesterDisplay(): string
    {
        $roleLabel = trim((string) $this->ticket['requester_role_label']);
        $submittedByName = trim((string) $this->ticket['submitted_by_name']);

        if ($submittedByName === '' || $submittedByName === 'Public submission') {
            return $roleLabel;
        }

        return $roleLabel.' #'.$submittedByName;
    }

    private function logoUrl(): string
    {
        $configuredLogo = trim((string) env('MAIL_LOGO_URL', ''));

        if ($configuredLogo !== '') {
            return $configuredLogo;
        }

        return rtrim((string) config('app.url', ''), '/').'/images/qwerty-logo.png';
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
            self::TYPE_CREATED => 'Service Ticket #'.$this->ticket['id'].' Created - '.$this->ticket['title'],
            self::TYPE_RESPONDED => 'Service Ticket #'.$this->ticket['id'].' Updated - '.$this->ticket['title'],
            self::TYPE_DELETED => 'Service Ticket #'.$this->ticket['id'].' Deleted - '.$this->ticket['title'],
            default => 'Service Ticket #'.$this->ticket['id'].' Updated - '.$this->ticket['title'],
        };
    }

    private function summaryLine(): string
    {
        return match ($this->changeType) {
            self::TYPE_CREATED => 'A new service ticket has been submitted and requires attention.',
            self::TYPE_RESPONDED => 'A new response has been added to this service ticket.',
            self::TYPE_DELETED => 'This service ticket has been deleted.',
            default => 'This service ticket has been updated.',
        };
    }
}
