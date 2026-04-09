<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
    public static function serviceTypes(): array
    {
        return ServiceDeskSetting::projectOptions()['service_types'];
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

    public function serviceTickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }
}
