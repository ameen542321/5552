<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEventActivity extends Model
{
    protected $guarded = [];

    public function event(): BelongsTo { return $this->belongsTo(SecurityEvent::class, 'security_event_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
