<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_user_edit_page_redirects_back_to_user_list(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'permissions' => array_keys(User::availablePermissions()),
        ]);

        $this->actingAs($admin)
            ->get(route('users.edit', 999))
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors(['user' => 'The selected user could not be found. It may have been deleted.']);
    }
}
