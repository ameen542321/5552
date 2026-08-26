<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model
{
    protected $fillable = ['support_ticket_id', 'sender_role', 'sender_id', 'message'];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function getSenderLabelAttribute(): string
    {
        return match ($this->sender_role) {
            'owner' => 'المالك',
            'support' => 'الدعم التقني',
            default => 'النظام',
        };
    }
}
