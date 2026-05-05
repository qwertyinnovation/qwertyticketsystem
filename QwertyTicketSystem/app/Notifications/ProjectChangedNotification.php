<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProjectChangedNotification extends Notification
{
    use Queueable;

    public const TYPE_CREATED = 'created';

    public const TYPE_UPDATED = 'updated';

    public const TYPE_DELETED = 'deleted';

    /**
     * @param  array{
     *     id: int,
     *     name: string,
     *     category: string,
     *     service_type: string,
     *     priority: string,
     *     status: string,
     *     description: string
     * }  $project
     */
    public function __construct(
        private readonly array $project,
        private readonly string $changeType,
        private readonly ?string $actorName = null,
    ) {
    }

    public static function fromProject(Project $project, string $changeType, ?User $actor = null): self
    {
        return new self(
            [
                'id' => (int) $project->id,
                'name' => self::displayText($project->name, 'Unnamed Project'),
                'category' => self::displayText($project->category, 'Unknown'),
                'service_type' => self::displayText($project->service_type, 'Unknown'),
                'priority' => self::displayText($project->priority, 'Unknown'),
                'status' => self::displayText($project->status, 'Unknown'),
                'description' => self::displayText($project->description, 'No description provided.'),
            ],
            $changeType,
            $actor?->name,
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
            ->line('Project ID: #'.$this->project['id'])
            ->line('Project: '.$this->project['name'])
            ->line('Category: '.$this->project['category'])
            ->line('Service Type: '.$this->project['service_type'])
            ->line('Priority: '.$this->project['priority'])
            ->line('Current Status: '.$this->project['status'])
            ->line('Description: '.Str::limit($this->project['description'], 500));

        if ($this->actorName !== null && trim($this->actorName) !== '') {
            $mail->line('Updated By: '.$this->actorName);
        }

        if ($this->changeType === self::TYPE_DELETED) {
            return $mail
                ->line('This project has been removed from the system.')
                ->salutation('Regards, Qwerty Service Team');
        }

        return $mail
            ->action('Open Project', route('projects.show', $this->project['id']))
            ->salutation('Regards, Qwerty Service Team');
    }

    public function changeType(): string
    {
        return $this->changeType;
    }

    public function projectId(): int
    {
        return $this->project['id'];
    }

    private function subjectLine(): string
    {
        return match ($this->changeType) {
            self::TYPE_CREATED => 'Project Created - '.$this->project['name'],
            self::TYPE_UPDATED => 'Project Updated - '.$this->project['name'],
            self::TYPE_DELETED => 'Project Deleted - '.$this->project['name'],
            default => 'Project Updated - '.$this->project['name'],
        };
    }

    private function summaryLine(): string
    {
        return match ($this->changeType) {
            self::TYPE_CREATED => 'A new project has been created in the system.',
            self::TYPE_UPDATED => 'Project details have been updated.',
            self::TYPE_DELETED => 'This project has been deleted from the system.',
            default => 'Project details have been changed.',
        };
    }

    private function logoUrl(): string
    {
        $configuredLogo = trim((string) env('MAIL_LOGO_URL', ''));

        if ($configuredLogo !== '') {
            return $configuredLogo;
        }

        return rtrim((string) config('app.url', ''), '/').'/images/qwerty-logo.png';
    }

    private static function displayText(?string $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : $fallback;
    }
}
