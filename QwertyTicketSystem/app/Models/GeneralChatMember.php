<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralChatMember extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_KICKED = 'kicked';

    protected $fillable = [
        'user_id',
        'status',
        'approved_by',
        'invited_by',
        'kicked_by',
        'kick_reason',
        'requested_at',
        'approved_at',
        'joined_at',
        'kicked_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'joined_at' => 'datetime',
        'kicked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function kickedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kicked_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public static function userCanAccess(User $user): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        return self::query()
            ->where('user_id', $user->id)
            ->where('status', self::STATUS_APPROVED)
            ->exists();
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
