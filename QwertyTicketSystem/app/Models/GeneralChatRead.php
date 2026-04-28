<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralChatRead extends Model
{
    protected $fillable = [
        'user_id',
        'last_read_message_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(GeneralChatMessage::class, 'last_read_message_id');
    }
}
