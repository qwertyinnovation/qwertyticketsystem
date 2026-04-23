<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Project extends Model
{
    public const DEFAULT_CATEGORIES = ['Infrastructure', 'Application', 'Security', 'Network', 'Support'];
    public const DEFAULT_SERVICE_TYPES = ['Bug Fix', 'Feature Request', 'Maintenance', 'Incident', 'Consultation'];
    public const DEFAULT_PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];
    public const DEFAULT_STATUSES = ['Open', 'In Progress', 'On Hold', 'Completed', 'Closed'];

    protected $fillable = [
        'name',
        'category',
        'service_type',
        'priority',
        'status',
        'description',
    ];

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return ServiceDeskSetting::projectOptions()['categories'];
    }

    /**
     * @return array<int, string>
     */
    public static function categoriesWithHistorical(): array
    {
        return self::mergeUniqueValues(self::categories(), self::historicalValues('category'));
    }

    /**
     * @return array<int, string>
     */
    public static function categoriesForEdit(self $project): array
    {
        return self::mergeUniqueValues(self::categories(), [$project->category]);
    }

    /**
     * @return array<int, string>
     */
    public static function serviceTypes(): array
    {
        return ServiceDeskSetting::projectOptions()['service_types'];
    }

    /**
     * @return array<int, string>
     */
    public static function serviceTypesWithHistorical(): array
    {
        return self::mergeUniqueValues(self::serviceTypes(), self::historicalValues('service_type'));
    }

    /**
     * @return array<int, string>
     */
    public static function serviceTypesForEdit(self $project): array
    {
        return self::mergeUniqueValues(self::serviceTypes(), [$project->service_type]);
    }

    /**
     * @return array<int, string>
     */
    public static function priorities(): array
    {
        return self::DEFAULT_PRIORITIES;
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return ServiceDeskSetting::projectOptions()['statuses'];
    }

    /**
     * @return array<int, string>
     */
    public static function statusesWithHistorical(): array
    {
        return self::mergeUniqueValues(self::statuses(), self::historicalValues('status'));
    }

    /**
     * @return array<int, string>
     */
    public static function statusesForEdit(self $project): array
    {
        return self::mergeUniqueValues(self::statuses(), [$project->status]);
    }

    public function serviceTickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ProjectMessage::class)->latestOfMany();
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user_assignments')
            ->withTimestamps();
    }

    /**
     * @return Collection<int, User>
     */
    public function chatParticipants(): Collection
    {
        return User::query()
            ->where(function ($query): void {
                $query->whereIn('role', [User::ROLE_ADMIN, User::ROLE_PM])
                    ->orWhereHas('assignedProjects', function ($assignedProjectsQuery): void {
                        $assignedProjectsQuery->where('projects.id', $this->id);
                    });
            })
            ->orderByRaw(
                "case role
                    when '".User::ROLE_ADMIN."' then 0
                    when '".User::ROLE_PM."' then 1
                    when '".User::ROLE_INTERNAL."' then 2
                    when '".User::ROLE_VENDOR."' then 3
                    when '".User::ROLE_CLIENT."' then 4
                    else 5
                end"
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);
    }

    public function hasChatParticipant(User $user): bool
    {
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_PM], true)) {
            return true;
        }

        return $this->assignedUsers()
            ->where('users.id', $user->id)
            ->exists();
    }

    public function requiresTicketTermsAcceptance(): bool
    {
        $status = self::normalizeComparisonValue($this->status);

        if ($status === '') {
            return false;
        }

        foreach (ServiceDeskSetting::ticketTermsTriggerStatuses() as $triggerStatus) {
            if ($status === self::normalizeComparisonValue($triggerStatus)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeComparisonValue(?string $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();
    }

    /**
     * @param  array<int, string>  ...$lists
     * @return array<int, string>
     */
    private static function mergeUniqueValues(array ...$lists): array
    {
        $merged = [];

        foreach ($lists as $list) {
            foreach ($list as $value) {
                $trimmed = trim($value);

                if ($trimmed === '' || in_array($trimmed, $merged, true)) {
                    continue;
                }

                $merged[] = $trimmed;
            }
        }

        return $merged;
    }

    /**
     * @return array<int, string>
     */
    private static function historicalValues(string $column): array
    {
        return self::query()
            ->select($column)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }
}
