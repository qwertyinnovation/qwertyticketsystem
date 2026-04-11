<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user_assignments')
            ->withTimestamps();
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
