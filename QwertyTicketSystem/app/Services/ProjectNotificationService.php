<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectChangedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProjectNotificationService
{
    public function projectCreated(Project $project, ?User $actor = null): void
    {
        $this->sendProjectNotification($project, ProjectChangedNotification::TYPE_CREATED, $actor);
    }

    public function projectUpdated(Project $project, ?User $actor = null): void
    {
        $this->sendProjectNotification($project, ProjectChangedNotification::TYPE_UPDATED, $actor);
    }

    public function projectDeleted(Project $project, ?User $actor = null): void
    {
        $this->sendProjectNotification($project, ProjectChangedNotification::TYPE_DELETED, $actor);
    }

    private function sendProjectNotification(Project $project, string $changeType, ?User $actor): void
    {
        try {
            $recipients = $this->projectRecipients($project);

            if ($recipients->isEmpty()) {
                return;
            }

            $notification = ProjectChangedNotification::fromProject($project, $changeType, $actor);

            foreach ($recipients as $recipient) {
                try {
                    $recipient->notify($notification);
                } catch (Throwable $exception) {
                    Log::warning('Project email notification failed for recipient.', [
                        'project_id' => $project->id,
                        'change_type' => $changeType,
                        'recipient_email' => $recipient->email,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Project email notification failed.', [
                'project_id' => $project->id,
                'change_type' => $changeType,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function projectRecipients(Project $project): Collection
    {
        $projectId = (int) $project->id;

        $users = User::query()
            ->where(function ($query) use ($projectId): void {
                $query->whereIn('role', [User::ROLE_ADMIN, User::ROLE_PM])
                    ->orWhereJsonContains('permissions', User::PERMISSION_MANAGE_PROJECTS)
                    ->orWhereHas('assignedProjects', function ($assignedProjectsQuery) use ($projectId): void {
                        $assignedProjectsQuery->where('projects.id', $projectId);
                    });
            })
            ->get(['id', 'name', 'email', 'role', 'permissions']);

        return $users
            ->filter(static fn (User $user): bool => is_string($user->email) && trim($user->email) !== '')
            ->unique(static fn (User $user): string => strtolower(trim($user->email)))
            ->values();
    }
}

