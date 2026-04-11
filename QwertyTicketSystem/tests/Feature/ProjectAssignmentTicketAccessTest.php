<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\ServiceTicketResponse;
use App\Models\ServiceTicketPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProjectAssignmentTicketAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_staff_can_view_and_respond_only_for_assigned_project_tickets(): void
    {
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $ticketOwner = $this->userWithRole(User::ROLE_CLIENT);

        $assignedProject = $this->createProject('Assigned Project');
        $otherProject = $this->createProject('Other Project');

        $assignedProject->assignedUsers()->sync([$internalUser->id]);

        $assignedTicket = $this->createTicket($assignedProject, $ticketOwner, 'Assigned Ticket');
        $otherTicket = $this->createTicket($otherProject, $ticketOwner, 'Other Ticket');

        $this->actingAs($internalUser)
            ->get(route('service-tickets.index'))
            ->assertOk()
            ->assertSeeText('Assigned Ticket')
            ->assertDontSeeText('Other Ticket');

        $this->actingAs($internalUser)
            ->get(route('service-tickets.create'))
            ->assertOk()
            ->assertSeeText('Assigned Project')
            ->assertDontSeeText('Other Project');

        $this->actingAs($internalUser)
            ->get(route('service-tickets.show', $assignedTicket))
            ->assertOk();

        $this->actingAs($internalUser)
            ->get(route('service-tickets.show', $otherTicket))
            ->assertForbidden();

        $this->actingAs($internalUser)
            ->put(route('service-tickets.response.update', $assignedTicket), [
                'status' => 'Resolved',
                'response_message' => 'Handled by assigned internal staff',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('service_tickets', [
            'id' => $assignedTicket->id,
            'status' => 'Resolved',
        ]);

        $this->assertDatabaseHas('service_ticket_responses', [
            'service_ticket_id' => $assignedTicket->id,
            'response_message' => 'Handled by assigned internal staff',
        ]);

        $this->actingAs($internalUser)
            ->put(route('service-tickets.response.update', $assignedTicket), [
                'status' => 'Closed',
                'response_message' => 'Final closure update',
            ])
            ->assertRedirect();

        $this->assertSame('Closed', $assignedTicket->fresh()->status);
        $this->assertSame(
            2,
            ServiceTicketResponse::query()->where('service_ticket_id', $assignedTicket->id)->count()
        );

        $this->actingAs($internalUser)
            ->get(route('service-tickets.show', $assignedTicket))
            ->assertOk()
            ->assertSeeTextInOrder([
                'Handled by assigned internal staff',
                'Final closure update',
            ]);

        $this->actingAs($internalUser)
            ->put(route('service-tickets.response.update', $otherTicket), [
                'status' => 'Resolved',
                'response_message' => 'Should be forbidden',
            ])
            ->assertForbidden();
    }

    public function test_response_attachment_allows_only_safe_document_and_image_types(): void
    {
        $adminUser = $this->userWithRole(User::ROLE_ADMIN);
        $ticketOwner = $this->userWithRole(User::ROLE_CLIENT);
        $project = $this->createProject('Attachment Validation Project');
        $ticket = $this->createTicket($project, $ticketOwner, 'Attachment Validation Ticket');

        $this->actingAs($adminUser)
            ->from(route('service-tickets.show', $ticket))
            ->put(route('service-tickets.response.update', $ticket), [
                'status' => 'In Progress',
                'response_message' => 'Blocked attachment',
                'response_attachment' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ])
            ->assertRedirect(route('service-tickets.show', $ticket))
            ->assertSessionHasErrors('response_attachment');

        $this->actingAs($adminUser)
            ->put(route('service-tickets.response.update', $ticket), [
                'status' => 'In Progress',
                'response_message' => 'PDF attachment',
                'response_attachment' => UploadedFile::fake()->create('report.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($adminUser)
            ->put(route('service-tickets.response.update', $ticket), [
                'status' => 'Resolved',
                'response_message' => 'Image attachment',
                'response_attachment' => UploadedFile::fake()->image('evidence.png'),
            ])
            ->assertRedirect();

        $this->assertSame('Resolved', $ticket->fresh()->status);
        $this->assertSame(
            2,
            ServiceTicketResponse::query()->where('service_ticket_id', $ticket->id)->count()
        );
    }

    public function test_assigned_client_can_submit_and_view_assigned_project_tickets_but_cannot_respond(): void
    {
        $clientUser = $this->userWithRole(User::ROLE_CLIENT);
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);

        $assignedProject = $this->createProject('Client Assigned Project');
        $otherProject = $this->createProject('Client Other Project');

        $assignedProject->assignedUsers()->sync([$clientUser->id, $internalUser->id]);

        $assignedProjectTicket = $this->createTicket(
            $assignedProject,
            $internalUser,
            'Assigned Project Existing Ticket',
            'Resolved',
            'Existing response for assigned project'
        );

        $otherProjectTicket = $this->createTicket(
            $otherProject,
            $internalUser,
            'Other Project Existing Ticket',
            'Resolved',
            'Other project response'
        );

        $this->actingAs($clientUser)
            ->get(route('service-tickets.index'))
            ->assertOk()
            ->assertSeeText('Assigned Project Existing Ticket')
            ->assertDontSeeText('Other Project Existing Ticket');

        $this->actingAs($clientUser)
            ->get(route('service-tickets.create'))
            ->assertOk()
            ->assertSeeText('Client Assigned Project')
            ->assertDontSeeText('Client Other Project');

        $this->actingAs($clientUser)
            ->get(route('service-tickets.show', $assignedProjectTicket))
            ->assertOk()
            ->assertSeeText('Existing response for assigned project');

        $this->actingAs($clientUser)
            ->get(route('service-tickets.show', $otherProjectTicket))
            ->assertForbidden();

        $this->actingAs($clientUser)
            ->post(route('service-tickets.store'), [
                'project_id' => $assignedProject->id,
                'title' => 'Client New Ticket',
                'description' => 'Client can submit on assigned project',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('service_tickets', [
            'project_id' => $assignedProject->id,
            'submitted_by_user_id' => $clientUser->id,
            'title' => 'Client New Ticket',
        ]);

        $this->actingAs($clientUser)
            ->from(route('service-tickets.create'))
            ->post(route('service-tickets.store'), [
                'project_id' => $otherProject->id,
                'title' => 'Forbidden Project Ticket',
                'description' => 'Should fail validation',
            ])
            ->assertRedirect(route('service-tickets.create'))
            ->assertSessionHasErrors('project_id');

        $this->actingAs($clientUser)
            ->put(route('service-tickets.response.update', $assignedProjectTicket), [
                'status' => 'Closed',
                'response_message' => 'Client should not respond',
            ])
            ->assertForbidden();
    }

    public function test_project_create_and_update_sync_assigned_users(): void
    {
        $projectManager = $this->userWithRole(User::ROLE_PM);
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $vendorUser = $this->userWithRole(User::ROLE_VENDOR);
        $clientUser = $this->userWithRole(User::ROLE_CLIENT);

        $this->actingAs($projectManager)
            ->post(route('projects.store'), [
                'name' => 'Assignment Sync Project',
                'category' => Project::DEFAULT_CATEGORIES[0],
                'service_type' => Project::DEFAULT_SERVICE_TYPES[0],
                'priority' => Project::DEFAULT_PRIORITIES[0],
                'status' => Project::DEFAULT_STATUSES[0],
                'description' => 'Initial assignment payload',
                'assignee_user_ids' => [$internalUser->id, $vendorUser->id],
            ])
            ->assertRedirect(route('projects.index'));

        $project = Project::query()->where('name', 'Assignment Sync Project')->firstOrFail();

        $this->assertSame(
            [$internalUser->id, $vendorUser->id],
            $project->assignedUsers()->orderBy('users.id')->pluck('users.id')->map(static fn ($value): int => (int) $value)->all()
        );

        $this->actingAs($projectManager)
            ->put(route('projects.update', $project), [
                'name' => 'Assignment Sync Project',
                'category' => $project->category,
                'service_type' => $project->service_type,
                'priority' => $project->priority,
                'status' => $project->status,
                'description' => 'Updated assignment payload',
                'assignee_user_ids' => [$clientUser->id],
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame(
            [$clientUser->id],
            $project->assignedUsers()->orderBy('users.id')->pluck('users.id')->map(static fn ($value): int => (int) $value)->all()
        );
    }

    public function test_ticket_submission_attachment_allows_safe_document_and_image_types_only(): void
    {
        $clientUser = $this->userWithRole(User::ROLE_CLIENT);
        $project = $this->createProject('Attachment Upload Project');
        $project->assignedUsers()->sync([$clientUser->id]);

        $this->actingAs($clientUser)
            ->from(route('service-tickets.create'))
            ->post(route('service-tickets.store'), [
                'project_id' => $project->id,
                'title' => 'Blocked Upload Ticket',
                'description' => 'Should reject executable',
                'photos' => [
                    UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
                ],
            ])
            ->assertRedirect(route('service-tickets.create'))
            ->assertSessionHasErrors('photos.0');

        $this->actingAs($clientUser)
            ->post(route('service-tickets.store'), [
                'project_id' => $project->id,
                'title' => 'Safe Upload Ticket',
                'description' => 'PDF and image should pass',
                'photos' => [
                    UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
                    UploadedFile::fake()->image('evidence.png'),
                ],
            ])
            ->assertRedirect();

        $ticket = ServiceTicket::query()->where('title', 'Safe Upload Ticket')->firstOrFail();

        $this->assertSame(
            2,
            ServiceTicketPhoto::query()->where('service_ticket_id', $ticket->id)->count()
        );
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

    private function createTicket(
        Project $project,
        User $submittedBy,
        string $title,
        string $status = 'New',
        ?string $responseDescription = null
    ): ServiceTicket {
        $ticket = ServiceTicket::query()->create([
            'project_id' => $project->id,
            'requester_role' => User::ROLE_CLIENT,
            'submitted_by_user_id' => $submittedBy->id,
            'title' => $title,
            'description' => $title.' description',
            'status' => $status,
        ]);

        if ($responseDescription !== null) {
            ServiceTicketResponse::query()->create([
                'service_ticket_id' => $ticket->id,
                'responded_by_user_id' => $submittedBy->id,
                'status' => $status,
                'response_message' => $responseDescription,
            ]);
        }

        return $ticket;
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'permissions' => User::defaultPermissionsForRole($role),
        ]);
    }
}
