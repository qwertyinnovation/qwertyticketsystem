<?php

namespace Tests\Feature;

use App\Events\ProjectMessageCreated;
use App\Events\ProjectMessageDeleted;
use App\Events\ProjectMessageUpdated;
use App\Models\Project;
use App\Models\ProjectChatRead;
use App\Models\ProjectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProjectChatAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_pm_auto_join_all_project_chat_rooms(): void
    {
        $adminUser = $this->userWithRole(User::ROLE_ADMIN);
        $projectManager = $this->userWithRole(User::ROLE_PM);
        $project = $this->createProject('Auto Joined Chat Project');

        $this->actingAs($adminUser)
            ->get(route('project-chat.index'))
            ->assertOk()
            ->assertSeeText('Auto Joined Chat Project');

        $this->actingAs($adminUser)
            ->get(route('project-chat.show', $project))
            ->assertOk()
            ->assertSeeText($adminUser->name)
            ->assertSeeText('Auto Joined');

        $this->actingAs($projectManager)
            ->get(route('project-chat.index'))
            ->assertOk()
            ->assertSeeText('Auto Joined Chat Project');

        $this->actingAs($projectManager)
            ->get(route('project-chat.show', $project))
            ->assertOk()
            ->assertSeeText($projectManager->name)
            ->assertSeeText('Auto Joined');
    }

    public function test_assigned_user_can_access_only_assigned_project_chat_and_send_messages(): void
    {
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $assignedProject = $this->createProject('Assigned Chat Project');
        $otherProject = $this->createProject('Other Chat Project');

        $assignedProject->assignedUsers()->sync([$internalUser->id]);

        $this->actingAs($internalUser)
            ->get(route('project-chat.index'))
            ->assertOk()
            ->assertSeeText('Assigned Chat Project')
            ->assertDontSeeText('Other Chat Project');

        $this->actingAs($internalUser)
            ->get(route('project-chat.show', $assignedProject))
            ->assertOk();

        $this->actingAs($internalUser)
            ->get(route('project-chat.show', $otherProject))
            ->assertForbidden();

        $this->actingAs($internalUser)
            ->post(route('project-chat.store', $assignedProject), [
                'message' => 'Checking in from the assigned room.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_messages', [
            'project_id' => $assignedProject->id,
            'user_id' => $internalUser->id,
            'message' => 'Checking in from the assigned room.',
        ]);

        $this->actingAs($internalUser)
            ->post(route('project-chat.store', $otherProject), [
                'message' => 'This should fail.',
            ])
            ->assertForbidden();
    }

    public function test_assigned_user_can_send_chat_message_over_json_without_page_reload(): void
    {
        Event::fake([ProjectMessageCreated::class]);

        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $project = $this->createProject('Real Time Chat Project');
        $project->assignedUsers()->sync([$internalUser->id]);

        $this->actingAs($internalUser)
            ->postJson(route('project-chat.store', $project), [
                'message' => 'Real-time chat update.',
            ])
            ->assertCreated()
            ->assertJsonPath('message.project_id', $project->id)
            ->assertJsonPath('message.user_id', $internalUser->id)
            ->assertJsonPath('message.message', 'Real-time chat update.')
            ->assertJsonPath('message.author_name', $internalUser->name);

        Event::assertDispatched(ProjectMessageCreated::class, function (ProjectMessageCreated $event) use ($project, $internalUser): bool {
            return (int) $event->projectMessage->project_id === (int) $project->id
                && (int) $event->projectMessage->user_id === (int) $internalUser->id
                && $event->projectMessage->message === 'Real-time chat update.';
        });
    }

    public function test_message_author_can_edit_and_delete_own_project_chat_message(): void
    {
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $project = $this->createProject('Editable Chat Project');
        $project->assignedUsers()->sync([$internalUser->id]);

        $message = ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $internalUser->id,
            'message' => 'Initial draft update.',
        ]);

        $this->actingAs($internalUser)
            ->put(route('project-chat.update', [$project, $message]), [
                'editing_message_id' => $message->id,
                'update_message' => 'Edited project update.',
            ])
            ->assertRedirect(route('project-chat.show', $project).'#message-'.$message->id);

        $this->assertDatabaseHas('project_messages', [
            'id' => $message->id,
            'message' => 'Edited project update.',
        ]);

        $this->actingAs($internalUser)
            ->delete(route('project-chat.destroy', [$project, $message]))
            ->assertRedirect(route('project-chat.show', $project));

        $this->assertDatabaseMissing('project_messages', [
            'id' => $message->id,
        ]);
    }

    public function test_message_author_can_update_chat_message_over_json_without_page_reload(): void
    {
        Event::fake([ProjectMessageUpdated::class]);

        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $project = $this->createProject('Editable Real Time Chat Project');
        $project->assignedUsers()->sync([$internalUser->id]);

        $message = ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $internalUser->id,
            'message' => 'Draft message.',
        ]);

        $this->actingAs($internalUser)
            ->putJson(route('project-chat.update', [$project, $message]), [
                'editing_message_id' => $message->id,
                'update_message' => 'Updated live message.',
            ])
            ->assertOk()
            ->assertJsonPath('message.id', $message->id)
            ->assertJsonPath('message.message', 'Updated live message.');

        Event::assertDispatched(ProjectMessageUpdated::class, function (ProjectMessageUpdated $event) use ($message): bool {
            return (int) $event->projectMessage->id === (int) $message->id
                && $event->projectMessage->message === 'Updated live message.';
        });
    }

    public function test_message_author_can_delete_chat_message_over_json_without_page_reload(): void
    {
        Event::fake([ProjectMessageDeleted::class]);

        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $project = $this->createProject('Delete Real Time Chat Project');
        $project->assignedUsers()->sync([$internalUser->id]);

        $message = ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $internalUser->id,
            'message' => 'Delete me.',
        ]);

        $this->actingAs($internalUser)
            ->deleteJson(route('project-chat.destroy', [$project, $message]))
            ->assertOk()
            ->assertJsonPath('message_id', $message->id);

        Event::assertDispatched(ProjectMessageDeleted::class, function (ProjectMessageDeleted $event) use ($message, $project): bool {
            return $event->messageId === (int) $message->id
                && $event->projectId === (int) $project->id;
        });
    }

    public function test_chat_lobby_shows_unread_badge_for_messages_from_other_users(): void
    {
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $projectManager = $this->userWithRole(User::ROLE_PM);
        $project = $this->createProject('Unread Badge Project');
        $project->assignedUsers()->sync([$internalUser->id]);

        ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $projectManager->id,
            'message' => 'First unread message.',
        ]);

        ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $internalUser->id,
            'message' => 'My own message should not count as unread.',
        ]);

        ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $projectManager->id,
            'message' => 'Second unread message.',
        ]);

        $this->actingAs($internalUser)
            ->get(route('project-chat.index'))
            ->assertOk()
            ->assertSeeText('Unread Badge Project')
            ->assertSeeText('2 unread');
    }

    public function test_opening_project_chat_room_marks_latest_message_as_read(): void
    {
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $projectManager = $this->userWithRole(User::ROLE_PM);
        $project = $this->createProject('Read Sync Project');
        $project->assignedUsers()->sync([$internalUser->id]);

        ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $projectManager->id,
            'message' => 'Older unread message.',
        ]);

        $latestMessage = ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $projectManager->id,
            'message' => 'Latest unread message.',
        ]);

        $this->actingAs($internalUser)
            ->get(route('project-chat.show', $project))
            ->assertOk();

        $this->assertDatabaseHas('project_chat_reads', [
            'project_id' => $project->id,
            'user_id' => $internalUser->id,
            'last_read_message_id' => $latestMessage->id,
        ]);
    }

    public function test_assigned_user_can_mark_project_chat_messages_as_read_over_json(): void
    {
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $projectManager = $this->userWithRole(User::ROLE_PM);
        $project = $this->createProject('Read Endpoint Project');
        $project->assignedUsers()->sync([$internalUser->id]);

        $firstMessage = ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $projectManager->id,
            'message' => 'First message.',
        ]);

        $latestMessage = ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $projectManager->id,
            'message' => 'Latest message.',
        ]);

        ProjectChatRead::query()->create([
            'project_id' => $project->id,
            'user_id' => $internalUser->id,
            'last_read_message_id' => $firstMessage->id,
            'read_at' => now()->subMinute(),
        ]);

        $this->actingAs($internalUser)
            ->postJson(route('project-chat.read', $project), [
                'message_id' => $latestMessage->id,
            ])
            ->assertOk()
            ->assertJsonPath('read_message_id', $latestMessage->id);

        $this->assertDatabaseHas('project_chat_reads', [
            'project_id' => $project->id,
            'user_id' => $internalUser->id,
            'last_read_message_id' => $latestMessage->id,
        ]);
    }

    public function test_only_project_message_author_can_edit_and_delete_project_chat_message(): void
    {
        $adminUser = $this->userWithRole(User::ROLE_ADMIN);
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $project = $this->createProject('Moderated Chat Project');
        $project->assignedUsers()->sync([$internalUser->id]);

        $message = ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $internalUser->id,
            'message' => 'Please moderate this.',
        ]);

        $this->actingAs($adminUser)
            ->put(route('project-chat.update', [$project, $message]), [
                'editing_message_id' => $message->id,
                'update_message' => 'Admin updated this message.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('project_messages', [
            'id' => $message->id,
            'message' => 'Please moderate this.',
        ]);

        $this->actingAs($adminUser)
            ->delete(route('project-chat.destroy', [$project, $message]))
            ->assertForbidden();

        $this->actingAs($internalUser)
            ->put(route('project-chat.update', [$project, $message]), [
                'editing_message_id' => $message->id,
                'update_message' => 'Author updated this message.',
            ])
            ->assertRedirect(route('project-chat.show', $project).'#message-'.$message->id);

        $this->assertDatabaseHas('project_messages', [
            'id' => $message->id,
            'message' => 'Author updated this message.',
        ]);

        $this->actingAs($internalUser)
            ->delete(route('project-chat.destroy', [$project, $message]))
            ->assertRedirect(route('project-chat.show', $project));

        $this->assertDatabaseMissing('project_messages', [
            'id' => $message->id,
        ]);
    }

    public function test_assigned_non_author_cannot_edit_or_delete_another_users_message(): void
    {
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $vendorUser = $this->userWithRole(User::ROLE_VENDOR);
        $project = $this->createProject('Protected Chat Project');
        $project->assignedUsers()->sync([$internalUser->id, $vendorUser->id]);

        $message = ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $internalUser->id,
            'message' => 'Author-owned message.',
        ]);

        $this->actingAs($vendorUser)
            ->put(route('project-chat.update', [$project, $message]), [
                'editing_message_id' => $message->id,
                'update_message' => 'Unauthorized edit.',
            ])
            ->assertForbidden();

        $this->actingAs($vendorUser)
            ->delete(route('project-chat.destroy', [$project, $message]))
            ->assertForbidden();

        $this->assertDatabaseHas('project_messages', [
            'id' => $message->id,
            'message' => 'Author-owned message.',
        ]);
    }

    public function test_unassigned_user_cannot_open_project_chat_room(): void
    {
        $clientUser = $this->userWithRole(User::ROLE_CLIENT);
        $project = $this->createProject('Restricted Chat Project');

        ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $this->userWithRole(User::ROLE_PM)->id,
            'message' => 'Internal coordination message.',
        ]);

        $this->actingAs($clientUser)
            ->get(route('project-chat.index'))
            ->assertOk()
            ->assertDontSeeText('Restricted Chat Project');

        $this->actingAs($clientUser)
            ->get(route('project-chat.show', $project))
            ->assertForbidden();

        $this->actingAs($clientUser)
            ->post(route('project-chat.store', $project), [
                'message' => 'Let me in.',
            ])
            ->assertForbidden();
    }

    private function createProject(string $name): Project
    {
        return Project::query()->create([
            'name' => $name,
            'category' => Project::DEFAULT_CATEGORIES[0],
            'service_type' => Project::DEFAULT_SERVICE_TYPES[0],
            'priority' => Project::DEFAULT_PRIORITIES[0],
            'status' => Project::DEFAULT_STATUSES[0],
            'description' => $name.' description',
        ]);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'permissions' => User::defaultPermissionsForRole($role),
        ]);
    }
}
