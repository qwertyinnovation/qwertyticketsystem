<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\ServiceTicketPublicLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_delete_users_without_deleting_self(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $firstUser = $this->userWithRole(User::ROLE_CLIENT);
        $secondUser = $this->userWithRole(User::ROLE_INTERNAL);

        $this->actingAs($admin)
            ->delete(route('users.bulk-destroy'), [
                'selected_ids' => [$firstUser->id, $secondUser->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', '2 users deleted.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseMissing('users', ['id' => $firstUser->id]);
        $this->assertDatabaseMissing('users', ['id' => $secondUser->id]);
    }

    public function test_bulk_user_delete_rejects_current_user(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->delete(route('users.bulk-destroy'), [
                'selected_ids' => [$admin->id],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['user' => 'You cannot delete your own account.']);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_bulk_delete_projects(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $firstProject = $this->createProject('Bulk Delete Project A');
        $secondProject = $this->createProject('Bulk Delete Project B');

        $this->actingAs($admin)
            ->delete(route('projects.bulk-destroy'), [
                'selected_ids' => [$firstProject->id, $secondProject->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', '2 projects deleted.');

        $this->assertDatabaseMissing('projects', ['id' => $firstProject->id]);
        $this->assertDatabaseMissing('projects', ['id' => $secondProject->id]);
    }

    public function test_admin_can_bulk_delete_service_tickets(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $project = $this->createProject('Bulk Delete Ticket Project');
        $firstTicket = $this->createTicket($project, $admin, 'Bulk Ticket A');
        $secondTicket = $this->createTicket($project, $admin, 'Bulk Ticket B');

        $this->actingAs($admin)
            ->delete(route('service-tickets.bulk-destroy'), [
                'selected_ids' => [$firstTicket->id, $secondTicket->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', '2 service tickets deleted.');

        $this->assertDatabaseMissing('service_tickets', ['id' => $firstTicket->id]);
        $this->assertDatabaseMissing('service_tickets', ['id' => $secondTicket->id]);
    }

    public function test_user_can_bulk_delete_own_one_time_public_links(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $project = $this->createProject('Bulk Delete Public Link Project');
        $firstLink = $this->createPublicLink($project, $admin);
        $secondLink = $this->createPublicLink($project, $admin);

        $this->actingAs($admin)
            ->delete(route('service-tickets.public-links.bulk-destroy'), [
                'selected_ids' => [$firstLink->id, $secondLink->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', '2 one-time links deleted.');

        $this->assertDatabaseMissing('service_ticket_public_links', ['id' => $firstLink->id]);
        $this->assertDatabaseMissing('service_ticket_public_links', ['id' => $secondLink->id]);
    }

    public function test_user_cannot_bulk_delete_another_users_one_time_public_link(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $otherAdmin = $this->userWithRole(User::ROLE_ADMIN);
        $project = $this->createProject('Restricted Public Link Project');
        $link = $this->createPublicLink($project, $otherAdmin);

        $this->actingAs($admin)
            ->delete(route('service-tickets.public-links.bulk-destroy'), [
                'selected_ids' => [$link->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('service_ticket_public_links', ['id' => $link->id]);
    }

    public function test_user_cannot_bulk_delete_another_users_service_ticket(): void
    {
        $client = $this->userWithRole(User::ROLE_CLIENT);
        $otherClient = $this->userWithRole(User::ROLE_CLIENT);
        $project = $this->createProject('Restricted Bulk Ticket Project');
        $project->assignedUsers()->sync([$client->id, $otherClient->id]);
        $ticket = $this->createTicket($project, $otherClient, 'Other User Ticket');

        $this->actingAs($client)
            ->delete(route('service-tickets.bulk-destroy'), [
                'selected_ids' => [$ticket->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('service_tickets', ['id' => $ticket->id]);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'permissions' => User::defaultPermissionsForRole($role),
        ]);
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

    private function createTicket(Project $project, User $submittedBy, string $title): ServiceTicket
    {
        return ServiceTicket::query()->create([
            'project_id' => $project->id,
            'requester_role' => $submittedBy->role,
            'submitted_by_user_id' => $submittedBy->id,
            'title' => $title,
            'description' => $title.' description',
            'status' => ServiceTicket::statuses()[0],
            'public_tracking_token' => ServiceTicket::uniquePublicTrackingToken(),
        ]);
    }

    private function createPublicLink(Project $project, User $createdBy): ServiceTicketPublicLink
    {
        return ServiceTicketPublicLink::query()->create([
            'token' => ServiceTicketPublicLink::generateToken(),
            'project_id' => $project->id,
            'requester_role' => User::ROLE_CLIENT,
            'created_by_user_id' => $createdBy->id,
            'expires_at' => now()->addMinutes(30),
        ]);
    }
}
