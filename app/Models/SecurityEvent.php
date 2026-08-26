<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SecurityEvent extends Model
{
    public const OPEN_STATUSES = ['new', 'investigating', 'contained'];

    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'detected_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'contained_at' => 'datetime',
        'resolved_at' => 'datetime',
        'verified_at' => 'datetime',
        'response_expires_at' => 'datetime',
    ];

    public function actor(): MorphTo { return $this->morphTo(); }
    public function target(): MorphTo { return $this->morphTo(); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function acknowledger(): BelongsTo { return $this->belongsTo(User::class, 'acknowledged_by'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function activities(): HasMany { return $this->hasMany(SecurityEventActivity::class); }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function getMaskedSourceIpAttribute(): ?string
    {
        if (! $this->source_ip) {
            return null;
        }

        if (filter_var($this->source_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.xxx', $this->source_ip);
        }

        return str_contains($this->source_ip, ':')
            ? implode(':', array_slice(explode(':', $this->source_ip), 0, 3)).':…'
            : 'مصدر منقح';
    }

    public function getPlaybookAttribute(): array
    {
        return config("security_command_center.playbooks.{$this->category}")
            ?? config('security_command_center.playbooks.default', []);
    }
}
