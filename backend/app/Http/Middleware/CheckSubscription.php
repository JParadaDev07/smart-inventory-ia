<?php

namespace App\Http\Middleware;

use App\Services\Billing\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * @param  string|null  $plan  Optional: 'pro' to require Pro plan; otherwise only access (trial or active) is checked.
     */
    public function handle(Request $request, Closure $next, ?string $plan = null): Response
    {
        $user = $request->user();
        if (!$user?->business) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $subscription = $user->business->subscription;
        if ($subscription) {
            $this->subscriptionService->expireTrialIfEnded($subscription);
            $this->subscriptionService->expireIfPeriodEnded($subscription);
            $subscription->refresh();
        }
        if (!$subscription) {
            return response()->json([
                'message' => 'Your subscription has expired. Please renew.',
                'code' => 'SUBSCRIPTION_EXPIRED',
            ], 403);
        }

        if (!$subscription->hasAccess()) {
            return response()->json([
                'message' => 'Your subscription has expired. Please renew.',
                'code' => 'SUBSCRIPTION_EXPIRED',
            ], 403);
        }

        if ($plan === 'pro' && !$user->business->isOnProPlan()) {
            return response()->json([
                'message' => 'This feature requires a Pro or Enterprise subscription.',
                'code' => 'SUBSCRIPTION_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
