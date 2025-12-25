<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Embargo extends Model
{
    protected $table = 'embargo';

    protected $fillable = [
        'object_id',
        'embargo_type',
        'start_date',
        'end_date',
        'is_perpetual',
        'status',
        'created_by',
        'lifted_by',
        'lifted_at',
        'lift_reason',
        'notify_on_expiry',
        'notify_days_before',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_perpetual' => 'boolean',
        'lifted_at' => 'datetime',
        'notify_on_expiry' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(EmbargoI18n::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(EmbargoException::class);
    }

    public function auditLog(): HasMany
    {
        return $this->hasMany(EmbargoAudit::class);
    }

    public function getReasonAttribute(): ?string
    {
        $culture = sfContext::getInstance()->user->getCulture() ?? 'en';
        $translation = $this->translations->where('culture', $culture)->first()
            ?? $this->translations->where('culture', 'en')->first();
        return $translation?->reason;
    }

    public function getPublicMessageAttribute(): ?string
    {
        $culture = sfContext::getInstance()->user->getCulture() ?? 'en';
        $translation = $this->translations->where('culture', $culture)->first()
            ?? $this->translations->where('culture', 'en')->first();
        return $translation?->public_message;
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        $now = Carbon::now();
        if ($now->lt($this->start_date)) {
            return false;
        }
        if (!$this->is_perpetual && $this->end_date && $now->gt($this->end_date)) {
            return false;
        }
        return true;
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if ($this->is_perpetual || !$this->end_date) {
            return null;
        }
        return Carbon::now()->diffInDays($this->end_date, false);
    }

    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'active' => '<span class="badge bg-danger">Active</span>',
            'expired' => '<span class="badge bg-secondary">Expired</span>',
            'lifted' => '<span class="badge bg-success">Lifted</span>',
            'pending' => '<span class="badge bg-warning">Pending</span>',
        ];
        return $badges[$this->status] ?? '<span class="badge bg-secondary">Unknown</span>';
    }
}
