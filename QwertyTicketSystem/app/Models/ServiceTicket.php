<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ServiceTicket extends Model
{
    public const DEFAULT_STATUSES = ['New', 'In Progress', 'Waiting on Client', 'Resolved', 'Closed'];

    protected $fillable = [
        'project_id',
        'requester_role',
        'submitted_by_user_id',
        'title',
        'description',
        'custom_fields',
        'screenshot_path',
        'status',
        'response_description',
        'response_photo_path',
        'public_tracking_token',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ServiceTicketPhoto::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ServiceTicketResponse::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function latestResponse(): HasOne
    {
        return $this->hasOne(ServiceTicketResponse::class)->latestOfMany('created_at');
    }

    /**
     * @return array<string, string>
     */
    public static function requesterRoles(): array
    {
        return ServiceDeskSetting::roleLabels();
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return self::DEFAULT_STATUSES;
    }

    /**
     * @return array<string, array{enabled: bool, required: bool}>
     */
    public static function formSchemaForRole(string $role): array
    {
        $schema = ServiceDeskSetting::ticketFormSchema();

        return $schema[$role] ?? ServiceDeskSetting::defaultTicketFormSchema()[User::ROLE_CLIENT];
    }

    public static function generatePublicTrackingToken(): string
    {
        return Str::random(48);
    }

    public static function uniquePublicTrackingToken(): string
    {
        do {
            $token = self::generatePublicTrackingToken();
        } while (self::query()->where('public_tracking_token', $token)->exists());

        return $token;
    }

    public function ensurePublicTrackingToken(): string
    {
        if (is_string($this->public_tracking_token) && trim($this->public_tracking_token) !== '') {
            return $this->public_tracking_token;
        }

        $this->public_tracking_token = self::uniquePublicTrackingToken();
        $this->save();

        return $this->public_tracking_token;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
        ];
    }
}
