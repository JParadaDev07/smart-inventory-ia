<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Activate subscription after approved payment. Idempotent per payment reference.
     */
    public function activateFromPayment(int $businessId, string $plan, int $periodDays = 30): Subscription
    {
        return DB::transaction(function () use ($businessId, $plan, $periodDays) {
            $sub = Subscription::where('business_id', $businessId)->firstOrFail();
            $sub->update([
                'plan' => $plan,
                'status' => Subscription::STATUS_ACTIVE,
                'current_period_end' => now()->addDays($periodDays),
            ]);
            return $sub->fresh();
        });
    }

    /**
     * Mark subscription as expired when current period has ended (e.g. from a scheduled job or on-read).
     */
    public function expireIfPeriodEnded(Subscription $subscription): bool
    {
        if ($subscription->status !== Subscription::STATUS_ACTIVE) {
            return false;
        }
        if ($subscription->current_period_end?->isFuture()) {
            return false;
        }
        $subscription->update(['status' => Subscription::STATUS_EXPIRED]);
        return true;
    }

    /**
     * Ensure trial-ended businesses without payment are marked expired.
     */
    public function expireTrialIfEnded(Subscription $subscription): bool
    {
        if ($subscription->status !== Subscription::STATUS_TRIAL) {
            return false;
        }
        if ($subscription->trial_ends_at?->isFuture()) {
            return false;
        }
        $subscription->update(['status' => Subscription::STATUS_EXPIRED]);
        return true;
    }
}
