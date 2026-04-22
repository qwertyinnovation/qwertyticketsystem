<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\ServiceTicketResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceTicketFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_ticket_index_filters_by_service_type_created_date_and_latest_response_date(): void
    {
        $adminUser = $this->userWithRole(User::ROLE_ADMIN);
        $requester = $this->userWithRole(User::ROLE_CLIENT);

        $hospitalProject = $this->createProject('Hospital Portal', 'Hospital Management System');
        $inventoryProject = $this->createProject('Inventory Portal', 'Inventory Management System');

        $hospitalAprilTicket = $this->createTicket($hospitalProject, $requester, 'Hospital April Ticket', '2026-04-10 09:00:00');
        $inventoryAprilTicket = $this->createTicket($inventoryProject, $requester, 'Inventory April Ticket', '2026-04-11 10:00:00');
        $hospitalMarchTicket = $this->createTicket($hospitalProject, $requester, 'Hospital March Ticket', '2026-03-30 11:00:00');
        $hospitalLatestOutsideRangeTicket = $this->createTicket($hospitalProject, $requester, 'Hospital Latest Outside Range', '2026-04-12 12:00:00');

        $this->createResponse($hospitalAprilTicket, $adminUser, '2026-04-15 10:00:00');
        $this->createResponse($inventoryAprilTicket, $adminUser, '2026-04-15 11:00:00');
        $this->createResponse($hospitalMarchTicket, $adminUser, '2026-04-05 12:00:00');
        $this->createResponse($hospitalLatestOutsideRangeTicket, $adminUser, '2026-04-15 13:00:00');
        $this->createResponse($hospitalLatestOutsideRangeTicket, $adminUser, '2026-04-20 09:00:00');

        $this->actingAs($adminUser)
            ->get(route('service-tickets.index', ['service_type' => 'Hospital Management System']))
            ->assertOk()
            ->assertSeeText('Hospital April Ticket')
            ->assertSeeText('Hospital March Ticket')
            ->assertSeeText('Hospital Latest Outside Range')
            ->assertDontSeeText('Inventory April Ticket');

        $this->actingAs($adminUser)
            ->get(route('service-tickets.index', [
                'created_date_from' => '2026-04-01',
                'created_date_to' => '2026-04-12',
            ]))
            ->assertOk()
            ->assertSeeText('Hospital April Ticket')
            ->assertSeeText('Inventory April Ticket')
            ->assertSeeText('Hospital Latest Outside Range')
            ->assertDontSeeText('Hospital March Ticket');

        $this->actingAs($adminUser)
            ->get(route('service-tickets.index', [
                'response_date_from' => '2026-04-15',
                'response_date_to' => '2026-04-15',
            ]))
            ->assertOk()
            ->assertSeeText('Hospital April Ticket')
            ->assertSeeText('Inventory April Ticket')
            ->assertDontSeeText('Hospital March Ticket')
            ->assertDontSeeText('Hospital Latest Outside Range');
    }

    private function createProject(string $name, string $serviceType): Project
    {
        return Project::query()->create([
            'name' => $name,
            'category' => Project::DEFAULT_CATEGORIES[0],
            'service_type' => $serviceType,
            'priority' => Project::DEFAULT_PRIORITIES[0],
            'status' => Project::DEFAULT_STATUSES[0],
            'description' => $name.' description',
        ]);
    }

    private function createTicket(Project $project, User $submittedBy, string $title, string $createdAt): ServiceTicket
    {
        $ticket = ServiceTicket::query()->create([
            'project_id' => $project->id,
            'requester_role' => User::ROLE_CLIENT,
            'submitted_by_user_id' => $submittedBy->id,
            'title' => $title,
            'description' => $title.' description',
            'status' => ServiceTicket::statuses()[0],
        ]);

        $ticket->forceFill([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ])->saveQuietly();

        return $ticket->fresh();
    }

    private function createResponse(ServiceTicket $ticket, User $respondedBy, string $createdAt): ServiceTicketResponse
    {
        $response = ServiceTicketResponse::query()->create([
            'service_ticket_id' => $ticket->id,
            'responded_by_user_id' => $respondedBy->id,
            'status' => 'In Progress',
            'response_message' => 'Response created for filtering',
        ]);

        $response->forceFill([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ])->saveQuietly();

        return $response;
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'permissions' => User::defaultPermissionsForRole($role),
        ]);
    }
}
