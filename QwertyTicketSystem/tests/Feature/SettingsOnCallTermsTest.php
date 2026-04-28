<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ServiceDeskSetting;
use App\Models\ServiceTicketPublicLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsOnCallTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_can_customize_terms_text_and_trigger_service_types_for_public_form(): void
    {
        $projectManager = User::factory()->create([
            'role' => User::ROLE_PM,
            'permissions' => User::defaultPermissionsForRole(User::ROLE_PM),
        ]);

        $customTerms = 'By checking this box, I accept custom billable support terms.';
        $customServiceType = 'Billable Support';

        $this->actingAs($projectManager)
            ->put(route('settings.update'), [
                'project_categories' => implode("\n", Project::DEFAULT_CATEGORIES),
                'project_service_types' => implode("\n", array_merge(Project::DEFAULT_SERVICE_TYPES, [$customServiceType])),
                'project_statuses' => implode("\n", Project::DEFAULT_STATUSES),
                'public_link_expiry_minutes' => ServiceDeskSetting::defaultPublicLinkExpiryMinutes(),
                'ticket_terms_text' => $customTerms,
                'ticket_terms_trigger_service_types' => $customServiceType,
            ])
            ->assertRedirect();

        $this->assertSame($customTerms, ServiceDeskSetting::ticketTermsText());
        $this->assertSame([$customServiceType], ServiceDeskSetting::ticketTermsTriggerServiceTypes());

        $project = Project::query()->create([
            'name' => 'Configurable Terms Project',
            'category' => Project::DEFAULT_CATEGORIES[0],
            'service_type' => $customServiceType,
            'priority' => Project::DEFAULT_PRIORITIES[0],
            'status' => Project::DEFAULT_STATUSES[0],
            'description' => 'Project for public form configuration test',
        ]);

        $publicLink = ServiceTicketPublicLink::query()->create([
            'token' => 'configurable-ticket-terms-link',
            'project_id' => $project->id,
            'requester_role' => User::ROLE_CLIENT,
            'expires_at' => now()->addHour(),
        ]);

        $this->get(route('service-tickets.public.create', $publicLink))
            ->assertOk()
            ->assertSeeText('Terms and Conditions')
            ->assertSeeText($customTerms);
    }
}
