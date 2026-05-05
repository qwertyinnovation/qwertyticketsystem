<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProjectEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_create_notifies_admin_and_assigned_users(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $pm = $this->userWithRole(User::ROLE_PM);
        $assignedClient = $this->userWithRole(User::ROLE_CLIENT);
        $outsider = $this->userWithRole(User::ROLE_VENDOR);

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                'name' => 'Qwerty Service Project',
                'category' => Project::categories()[0],
                'service_type' => Project::serviceTypes()[0],
                'priority' => Project::priorities()[0],
                'status' => Project::statuses()[0],
                'description' => 'Project for notification test',
                'assignee_user_ids' => [$assignedClient->id],
            ])
            ->assertRedirect(route('projects.index'));

        $project = Project::query()->firstOrFail();

        foreach ([$admin, $pm, $assignedClient] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                ProjectChangedNotification::class,
                fn (ProjectChangedNotification $notification): bool => $notification->changeType() === ProjectChangedNotification::TYPE_CREATED
                    && $notification->projectId() === (int) $project->id,
            );
        }

        Notification::assertNotSentTo($outsider, ProjectChangedNotification::class);
    }

    public function test_project_update_notifies_admin_and_current_assignees(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $oldAssignee = $this->userWithRole(User::ROLE_CLIENT);
        $newAssignee = $this->userWithRole(User::ROLE_INTERNAL);

        $project = Project::query()->create([
            'name' => 'Legacy Project',
            'category' => Project::categories()[0],
            'service_type' => Project::serviceTypes()[0],
            'priority' => Project::priorities()[0],
            'status' => Project::statuses()[0],
            'description' => 'Before update',
        ]);

        $project->assignedUsers()->sync([$oldAssignee->id]);
        Notification::fake();

        $this->actingAs($admin)
            ->put(route('projects.update', $project), [
                'name' => 'Legacy Project Updated',
                'category' => Project::categoriesForEdit($project)[0],
                'service_type' => Project::serviceTypesForEdit($project)[0],
                'priority' => Project::priorities()[1],
                'status' => Project::statusesForEdit($project)[0],
                'description' => 'After update',
                'assignee_user_ids' => [$newAssignee->id],
            ])
            ->assertRedirect(route('projects.show', $project));

        Notification::assertSentTo(
            $admin,
            ProjectChangedNotification::class,
            fn (ProjectChangedNotification $notification): bool => $notification->changeType() === ProjectChangedNotification::TYPE_UPDATED
                && $notification->projectId() === (int) $project->id,
        );

        Notification::assertSentTo(
            $newAssignee,
            ProjectChangedNotification::class,
            fn (ProjectChangedNotification $notification): bool => $notification->changeType() === ProjectChangedNotification::TYPE_UPDATED
                && $notification->projectId() === (int) $project->id,
        );

        Notification::assertNotSentTo($oldAssignee, ProjectChangedNotification::class);
    }

    public function test_project_delete_notifies_admin_and_assignees(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $assignedUser = $this->userWithRole(User::ROLE_CLIENT);

        $project = Project::query()->create([
            'name' => 'Delete Project',
            'category' => Project::categories()[0],
            'service_type' => Project::serviceTypes()[0],
            'priority' => Project::priorities()[0],
            'status' => Project::statuses()[0],
            'description' => 'Project to delete',
        ]);

        $project->assignedUsers()->sync([$assignedUser->id]);

        $this->actingAs($admin)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        foreach ([$admin, $assignedUser] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                ProjectChangedNotification::class,
                fn (ProjectChangedNotification $notification): bool => $notification->changeType() === ProjectChangedNotification::TYPE_DELETED
                    && $notification->projectId() === (int) $project->id,
            );
        }
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'permissions' => User::defaultPermissionsForRole($role),
        ]);
    }
}

