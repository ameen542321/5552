<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportSession extends Model
{
    protected $fillable = [
        'support_ticket_id', 'admin_id', 'admin_name', 'admin_email', 'target_type', 'target_id',
        'target_name', 'target_role', 'reason', 'ticket_reference', 'started_at', 'expires_at', 'ended_at',
        'started_ip', 'ended_ip', 'user_agent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function target()
    {
        return $this->morphTo(__FUNCTION__, 'target_type', 'target_id');
    }

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}
