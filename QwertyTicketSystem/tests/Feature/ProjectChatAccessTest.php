<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_admin_can_edit_and_delete_other_users_project_chat_messages(): void
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
            ->assertRedirect(route('project-chat.show', $project).'#message-'.$message->id);

        $this->assertDatabaseHas('project_messages', [
            'id' => $message->id,
            'message' => 'Admin updated this message.',
        ]);

        $this->actingAs($adminUser)
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
