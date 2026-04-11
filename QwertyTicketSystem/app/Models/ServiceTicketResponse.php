<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceTicketResponse extends Model
{
    protected $fillable = [
        'service_ticket_id',
        'responded_by_user_id',
        'status',
        'response_message',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
    ];

    public function serviceTicket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class);
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }
}
