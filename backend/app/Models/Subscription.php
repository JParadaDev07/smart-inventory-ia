<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    public const STATUS_TRIAL = 'trial';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    protected $fillable = [
        'business_id',
        'plan',
        'status',
        'trial_ends_at',
        'current_period_end',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_end' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isPro(): bool
    {
        return in_array($this->plan, ['pro', 'enterprise'], true);
    }

    /** Whether the subscription allows access (trial within period, or active with current_period_end in future). */
    public function isActive(): bool
    {
        if ($this->status === self::STATUS_TRIAL && $this->trial_ends_at?->isFuture()) {
            return true;
        }
        if ($this->status === self::STATUS_ACTIVE && $this->current_period_end?->isFuture()) {
            return true;
        }
        return false;
    }

    public function hasAccess(): bool
    {
        return $this->isActive();
    }
}
