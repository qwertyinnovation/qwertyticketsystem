<?php

namespace App\Services;

use App\Models\ServiceTicket;
use App\Models\User;
use App\Notifications\ServiceTicketChangedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ServiceTicketNotificationService
{
    public function ticketCreated(ServiceTicket $ticket, ?User $actor = null): void
    {
        $this->sendTicketNotification($ticket, ServiceTicketChangedNotification::TYPE_CREATED, $actor);
    }

    public function ticketResponded(ServiceTicket $ticket, User $actor, ?string $previousStatus, ?string $message): void
    {
        $this->sendTicketNotification(
            $ticket,
            ServiceTicketChangedNotification::TYPE_RESPONDED,
            $actor,
            $previousStatus,
            $ticket->status,
            $message,
        );
    }

    public function ticketDeleted(ServiceTicket $ticket, User $actor): void
    {
        $this->sendTicketNotification($ticket, ServiceTicketChangedNotification::TYPE_DELETED, $actor);
    }

    private function sendTicketNotification(
        ServiceTicket $ticket,
        string $changeType,
        ?User $actor = null,
        ?string $previousStatus = null,
        ?string $newStatus = null,
        ?string $message = null,
    ): void {
        if ($changeType !== ServiceTicketChangedNotification::TYPE_DELETED) {
            $ticket->ensurePublicTrackingToken();
        }

        try {
            $recipients = $this->ticketRecipients($ticket, $actor);

            if ($recipients->isEmpty()) {
                return;
            }

            $notification = ServiceTicketChangedNotification::fromTicket(
                $ticket,
                $changeType,
                $actor,
                $previousStatus,
                $newStatus,
                $message,
            );

            foreach ($recipients as $recipient) {
                try {
                    $recipient->notify($notification);
                } catch (Throwable $exception) {
                    Log::warning('Service ticket email notification failed for recipient.', [
                        'ticket_id' => $ticket->id,
                        'change_type' => $changeType,
                        'recipient_email' => $recipient->email,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Service ticket email notification failed.', [
                'ticket_id' => $ticket->id,
                'change_type' => $changeType,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function ticketRecipients(ServiceTicket $ticket, ?User $actor): Collection
    {
        $projectId = (int) $ticket->project_id;
        $submittedByUserId = $ticket->submitted_by_user_id !== null ? (int) $ticket->submitted_by_user_id : null;
        $actorId = $this->shouldExcludeActor($ticket, $actor) ? (int) $actor->id : null;

        $users = User::query()
            ->where(function ($query) use ($projectId, $submittedByUserId): void {
                $query->whereIn('role', [User::ROLE_ADMIN, User::ROLE_PM])
                    ->orWhereJsonContains('permissions', User::PERMISSION_MANAGE_PROJECTS)
                    ->orWhereJsonContains('permissions', User::PERMISSION_MANAGE_SETTINGS)
                    ->orWhereHas('assignedProjects', function ($assignedProjectsQuery) use ($projectId): void {
                        $assignedProjectsQuery->where('projects.id', $projectId);
                    });

                if ($submittedByUserId !== null) {
                    $query->orWhere('id', $submittedByUserId);
                }
            })
            ->when($actorId !== null, function ($query) use ($actorId): void {
                $query->where('id', '!=', $actorId);
            })
            ->get(['id', 'name', 'email', 'role', 'permissions']);

        return $users
            ->filter(static fn (User $user): bool => is_string($user->email) && trim($user->email) !== '')
            ->unique(static fn (User $user): string => strtolower(trim($user->email)))
            ->values();
    }

    private function shouldExcludeActor(ServiceTicket $ticket, ?User $actor): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        return (int) $ticket->submitted_by_user_id !== (int) $actor->id;
    }
}
