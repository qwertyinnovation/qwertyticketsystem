<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ServiceDeskSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectLegacyCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_project_values_are_available_in_filters_and_can_be_used(): void
    {
        $this->actingAs($this->projectManager());

        ServiceDeskSetting::updateProjectOptions([
            'categories' => ['Infrastructure', 'Application'],
            'service_types' => ['Bug Fix', 'Feature Request'],
            'statuses' => ['Open', 'In Progress'],
        ]);

        Project::query()->create([
            'name' => 'Legacy Closed Project',
            'category' => 'Legacy Operations',
            'service_type' => 'Legacy Maintenance',
            'priority' => 'High',
            'status' => 'Archived',
            'description' => 'Historical category record',
        ]);

        Project::query()->create([
            'name' => 'Modern Active Project',
            'category' => 'Infrastructure',
            'service_type' => 'Feature Request',
            'priority' => 'Medium',
            'status' => 'Open',
            'description' => 'Active category record',
        ]);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('value="Legacy Operations"', false)
            ->assertSee('value="Legacy Maintenance"', false)
            ->assertSee('value="Archived"', false);

        $this->get(route('projects.index', ['category' => 'Legacy Operations']))
            ->assertOk()
            ->assertSeeText('Legacy Closed Project')
            ->assertDontSeeText('Modern Active Project');

        $this->get(route('projects.index', ['service_type' => 'Legacy Maintenance']))
            ->assertOk()
            ->assertSeeText('Legacy Closed Project')
            ->assertDontSeeText('Modern Active Project');

        $this->get(route('projects.index', ['status' => 'Archived']))
            ->assertOk()
            ->assertSeeText('Legacy Closed Project')
            ->assertDontSeeText('Modern Active Project');
    }

    public function test_updating_project_with_deleted_values_remains_valid(): void
    {
        $this->actingAs($this->projectManager());

        ServiceDeskSetting::updateProjectOptions([
            'categories' => ['Infrastructure'],
            'service_types' => ['Bug Fix'],
            'statuses' => ['Open'],
        ]);

        $project = Project::query()->create([
            'name' => 'Legacy Closed Project',
            'category' => 'Legacy Operations',
            'service_type' => 'Legacy Maintenance',
            'priority' => 'High',
            'status' => 'Archived',
            'description' => 'Historical category record',
        ]);

        $this->put(route('projects.update', $project), [
            'name' => 'Legacy Closed Project Updated',
            'category' => 'Legacy Operations',
            'service_type' => 'Legacy Maintenance',
            'priority' => 'Critical',
            'status' => 'Archived',
            'description' => 'Updated details',
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Legacy Closed Project Updated',
            'category' => 'Legacy Operations',
            'service_type' => 'Legacy Maintenance',
            'status' => 'Archived',
        ]);
    }

    private function projectManager(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_PM,
            'permissions' => User::defaultPermissionsForRole(User::ROLE_PM),
        ]);
    }
}
