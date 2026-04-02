<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@demo.local'],
            [
                'name' => 'Admin Demo',
                'password' => 'demo123',
                'role' => User::ROLE_ADMIN,
                'permissions' => array_keys(User::availablePermissions()),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'pm@demo.local'],
            [
                'name' => 'PM Demo',
                'password' => 'demo123',
                'role' => User::ROLE_PM,
                'permissions' => User::defaultPermissionsForRole(User::ROLE_PM),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'client@demo.local'],
            [
                'name' => 'Client Demo',
                'password' => 'demo123',
                'role' => User::ROLE_CLIENT,
                'permissions' => User::defaultPermissionsForRole(User::ROLE_CLIENT),
            ]
        );

        User::factory()->create([
            'name' => 'Support Staff',
            'email' => 'staff@example.com',
            'role' => User::ROLE_INTERNAL,
            'permissions' => User::defaultPermissionsForRole(User::ROLE_INTERNAL),
        ]);
    }
}
