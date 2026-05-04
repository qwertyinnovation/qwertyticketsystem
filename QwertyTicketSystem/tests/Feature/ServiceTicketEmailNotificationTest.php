<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\ServiceTicketPublicLink;
use App\Models\User;
use App\Notifications\ServiceTicketChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServiceTicketEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_service_ticket_notifies_related_users_by_email(): void
    {
        Notification::fake();

        $project = $this->createProject();
        $submitter = $this->userWithRole(User::ROLE_CLIENT);
        $assignedResponder = $this->userWithRole(User::ROLE_INTERNAL);
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $pm = $this->userWithRole(User::ROLE_PM);
        $outsider = $this->userWithRole(User::ROLE_VENDOR);

        $project->assignedUsers()->attach([$submitter->id, $assignedResponder->id]);

        $this->actingAs($submitter)
            ->post(route('service-tickets.store'), [
                'project_id' => $project->id,
                'title' => 'Patient portal issue',
                'description' => 'Client cannot open dashboard.',
            ])
            ->assertRedirect();

        $ticket = ServiceTicket::query()->firstOrFail();

        foreach ([$submitter, $admin, $pm, $assignedResponder] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                ServiceTicketChangedNotification::class,
                fn (ServiceTicketChangedNotification $notification): bool => $notification->changeType() === ServiceTicketChangedNotification::TYPE_CREATED
                    && $notification->ticketId() === (int) $ticket->id,
            );
        }

        Notification::assertNotSentTo($outsider, ServiceTicketChangedNotification::class);
    }

    public function test_ticket_response_notifies_submitter_and_project_stakeholders(): void
    {
        Notification::fake();

        $project = $this->createProject();
        $submitter = $this->userWithRole(User::ROLE_CLIENT);
        $responder = $this->userWithRole(User::ROLE_INTERNAL);
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $pm = $this->userWithRole(User::ROLE_PM);

        $project->assignedUsers()->attach([$submitter->id, $responder->id]);

        $ticket = ServiceTicket::query()->create([
            'project_id' => $project->id,
            'requester_role' => User::ROLE_CLIENT,
            'submitted_by_user_id' => $submitter->id,
            'title' => 'Printer is offline',
            'description' => 'Nurse station printer cannot print.',
            'status' => ServiceTicket::statuses()[0],
        ]);

        $this->actingAs($responder)
            ->put(route('service-tickets.response.update', $ticket), [
                'status' => 'In Progress',
                'response_message' => 'We are checking the printer queue.',
            ])
            ->assertRedirect();

        foreach ([$submitter, $admin, $pm] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                ServiceTicketChangedNotification::class,
                fn (ServiceTicketChangedNotification $notification): bool => $notification->changeType() === ServiceTicketChangedNotification::TYPE_RESPONDED
                    && $notification->ticketId() === (int) $ticket->id,
            );
        }

        Notification::assertNotSentTo($responder, ServiceTicketChangedNotification::class);
    }

    public function test_public_ticket_submission_notifies_project_stakeholders(): void
    {
        Notification::fake();

        $project = $this->createProject();
        $assignedResponder = $this->userWithRole(User::ROLE_INTERNAL);
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $pm = $this->userWithRole(User::ROLE_PM);
        $outsider = $this->userWithRole(User::ROLE_VENDOR);

        $project->assignedUsers()->attach($assignedResponder->id);

        $publicLink = ServiceTicketPublicLink::query()->create([
            'token' => 'public-submit-token',
            'project_id' => $project->id,
            'requester_role' => User::ROLE_CLIENT,
            'expires_at' => now()->addHour(),
        ]);

        $this->post(route('service-tickets.public.store', $publicLink), [
            'title' => 'Public ticket',
            'description' => 'Submitted from a one-time public link.',
        ])
            ->assertOk();

        $ticket = ServiceTicket::query()->firstOrFail();

        foreach ([$admin, $pm, $assignedResponder] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                ServiceTicketChangedNotification::class,
                fn (ServiceTicketChangedNotification $notification): bool => $notification->changeType() === ServiceTicketChangedNotification::TYPE_CREATED
                    && $notification->ticketId() === (int) $ticket->id,
            );
        }

        Notification::assertNotSentTo($outsider, ServiceTicketChangedNotification::class);
    }

    public function test_ticket_deletion_notifies_related_users_after_delete(): void
    {
        Notification::fake();

        $project = $this->createProject();
        $submitter = $this->userWithRole(User::ROLE_CLIENT);
        $assignedResponder = $this->userWithRole(User::ROLE_INTERNAL);
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $project->assignedUsers()->attach([$submitter->id, $assignedResponder->id]);

        $ticket = ServiceTicket::query()->create([
            'project_id' => $project->id,
            'requester_role' => User::ROLE_CLIENT,
            'submitted_by_user_id' => $submitter->id,
            'title' => 'Delete me',
            'description' => 'Deletion should trigger email.',
            'status' => ServiceTicket::statuses()[0],
        ]);

        $this->actingAs($admin)
            ->delete(route('service-tickets.destroy', $ticket))
            ->assertRedirect(route('service-tickets.index'));

        foreach ([$submitter, $assignedResponder] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                ServiceTicketChangedNotification::class,
                fn (ServiceTicketChangedNotification $notification): bool => $notification->changeType() === ServiceTicketChangedNotification::TYPE_DELETED
                    && $notification->ticketId() === (int) $ticket->id,
            );
        }

        Notification::assertNotSentTo($admin, ServiceTicketChangedNotification::class);
    }

    private function createProject(): Project
    {
        return Project::query()->create([
            'name' => 'Hospital Management System',
            'category' => Project::DEFAULT_CATEGORIES[0],
            'service_type' => Project::DEFAULT_SERVICE_TYPES[0],
            'priority' => Project::DEFAULT_PRIORITIES[0],
            'status' => Project::DEFAULT_STATUSES[0],
            'description' => 'Service ticket email notification test project.',
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
