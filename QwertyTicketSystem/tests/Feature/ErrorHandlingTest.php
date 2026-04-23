<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbidden_page_uses_friendly_error_screen(): void
    {
        config(['app.debug' => false]);

        $clientUser = $this->userWithRole(User::ROLE_CLIENT);
        $project = $this->createProject('Restricted Error Project');

        $this->actingAs($clientUser)
            ->get(route('project-chat.show', $project))
            ->assertForbidden()
            ->assertSeeText('Access denied')
            ->assertSeeText('You do not have access to this page.');
    }

    public function test_missing_page_uses_friendly_not_found_screen(): void
    {
        config(['app.debug' => false]);

        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSeeText('Page not found')
            ->assertSeeText('The page you are looking for could not be found.');
    }

    public function test_invalid_form_submission_shows_summary_error_message(): void
    {
        $clientUser = $this->userWithRole(User::ROLE_CLIENT);
        $project = $this->createProject('Validation Error Project');
        $project->assignedUsers()->sync([$clientUser->id]);

        $this->actingAs($clientUser)
            ->followingRedirects()
            ->from(route('service-tickets.create'))
            ->post(route('service-tickets.store'), [
                'project_id' => $project->id,
            ])
            ->assertOk()
            ->assertSeeText('Please review the highlighted fields and try again.')
            ->assertSeeText('The title field is required.')
            ->assertSeeText('The description field is required.');
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
