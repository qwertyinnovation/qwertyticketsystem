<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\ServiceTicketPublicLink;
use App\Models\ServiceTicketResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTicketPublicTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_submission_returns_read_only_tracking_link(): void
    {
        $project = Project::query()->create([
            'name' => 'Hospital Management System',
            'category' => Project::DEFAULT_CATEGORIES[0],
            'service_type' => 'Hospital Management System',
            'priority' => Project::DEFAULT_PRIORITIES[0],
            'status' => Project::DEFAULT_STATUSES[0],
            'description' => 'Public tracking project',
        ]);

        $publicLink = ServiceTicketPublicLink::query()->create([
            'token' => 'public-submit-token',
            'project_id' => $project->id,
            'requester_role' => User::ROLE_CLIENT,
            'expires_at' => now()->addHour(),
        ]);

        $submitResponse = $this->post(route('service-tickets.public.store', $publicLink), [
            'title' => 'Patient portal issue',
            'description' => 'Client cannot open dashboard',
        ]);

        $ticket = ServiceTicket::query()->firstOrFail();
        $trackingUrl = route('service-tickets.public.track', $ticket->public_tracking_token);

        $submitResponse
            ->assertOk()
            ->assertSeeText('Ticket Submitted')
            ->assertSeeText('Public Tracker Link')
            ->assertSee($trackingUrl, false);

        $this->assertNotNull($ticket->public_tracking_token);

        $responder = User::factory()->create([
            'role' => User::ROLE_INTERNAL,
            'permissions' => User::defaultPermissionsForRole(User::ROLE_INTERNAL),
        ]);

        ServiceTicketResponse::query()->create([
            'service_ticket_id' => $ticket->id,
            'responded_by_user_id' => $responder->id,
            'status' => 'In Progress',
            'response_message' => 'We are checking the issue now.',
        ]);

        $ticket->status = 'In Progress';
        $ticket->save();

        $this->get($trackingUrl)
            ->assertOk()
            ->assertSeeText('Public Tracker')
            ->assertSeeText('Ticket #'.$ticket->id)
            ->assertSeeText('Hospital Management System')
            ->assertSeeText('In Progress')
            ->assertSeeText('We are checking the issue now.')
            ->assertDontSeeText('Add Response')
            ->assertDontSeeText('Delete Ticket');

        $this->get(route('service-tickets.public.create', $publicLink))
            ->assertStatus(410)
            ->assertSeeText('This one-time link has already been used.');
    }
}
