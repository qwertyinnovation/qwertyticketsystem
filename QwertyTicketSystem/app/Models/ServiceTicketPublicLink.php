<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ServiceTicketPublicLink extends Model
{
    protected $fillable = [
        'token',
        'project_id',
        'requester_role',
        'created_by_user_id',
        'expires_at',
        'used_at',
        'used_by_ticket_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function usedByTicket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class, 'used_by_ticket_id');
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        /** @var Carbon $expiresAt */
        $expiresAt = $this->expires_at;

        return $expiresAt->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired();
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }
}
