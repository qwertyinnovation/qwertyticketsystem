<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PM = 'pm';

    public const ROLE_CLIENT = 'client';

    public const ROLE_INTERNAL = 'internal';

    public const ROLE_VENDOR = 'vendor';

    public const PROJECT_ASSIGNABLE_ROLES = [
        self::ROLE_CLIENT,
        self::ROLE_INTERNAL,
        self::ROLE_VENDOR,
    ];

    public const PERMISSION_VIEW_DASHBOARD = 'view_dashboard';

    public const PERMISSION_MANAGE_PROJECTS = 'manage_projects';

    public const PERMISSION_MANAGE_TICKETS = 'manage_tickets';

    public const PERMISSION_MANAGE_SETTINGS = 'manage_settings';

    public const PERMISSION_MANAGE_USERS = 'manage_users';

    public const PERMISSION_GENERATE_LINKS = 'generate_links';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'permissions',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'permissions' => 'array',
            'password' => 'hashed',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_PM => 'Project Manager',
            self::ROLE_CLIENT => 'Client',
            self::ROLE_INTERNAL => 'Internal Staff',
            self::ROLE_VENDOR => 'Third-Party Provider',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function availablePermissions(): array
    {
        return [
            self::PERMISSION_VIEW_DASHBOARD => 'View dashboard',
            self::PERMISSION_MANAGE_PROJECTS => 'Manage projects',
            self::PERMISSION_MANAGE_TICKETS => 'Manage tickets',
            self::PERMISSION_MANAGE_SETTINGS => 'Manage settings',
            self::PERMISSION_MANAGE_USERS => 'Manage users',
            self::PERMISSION_GENERATE_LINKS => 'Generate one-time links',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function defaultPermissionsForRole(string $role): array
    {
        return match ($role) {
            self::ROLE_ADMIN => array_keys(self::availablePermissions()),
            self::ROLE_PM => [
                self::PERMISSION_VIEW_DASHBOARD,
                self::PERMISSION_MANAGE_PROJECTS,
                self::PERMISSION_MANAGE_TICKETS,
                self::PERMISSION_MANAGE_SETTINGS,
                self::PERMISSION_GENERATE_LINKS,
            ],
            self::ROLE_CLIENT => [
                self::PERMISSION_VIEW_DASHBOARD,
                self::PERMISSION_MANAGE_TICKETS,
            ],
            self::ROLE_INTERNAL, self::ROLE_VENDOR => [
                self::PERMISSION_VIEW_DASHBOARD,
                self::PERMISSION_MANAGE_TICKETS,
            ],
            default => [self::PERMISSION_VIEW_DASHBOARD],
        };
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === self::ROLE_ADMIN) {
            return true;
        }

        return in_array($permission, $this->permissions ?? [], true);
    }

    /**
     * @return array<int, string>
     */
    public static function projectAssignableRoles(): array
    {
        return self::PROJECT_ASSIGNABLE_ROLES;
    }

    public function isProjectAssignableRole(): bool
    {
        return in_array($this->role, self::projectAssignableRoles(), true);
    }

    public function assignedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user_assignments')
            ->withTimestamps();
    }

    public function generalChatMembership(): HasOne
    {
        return $this->hasOne(GeneralChatMember::class);
    }

    public function generalChatMessages(): HasMany
    {
        return $this->hasMany(GeneralChatMessage::class);
    }

    /**
     * @return array<int, int>
     */
    public function assignedProjectIds(): array
    {
        return $this->assignedProjects()
            ->pluck('projects.id')
            ->map(static fn ($value): int => (int) $value)
            ->all();
    }
}
