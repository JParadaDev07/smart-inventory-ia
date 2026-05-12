<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('business.subscription');
        $sub = $user->business->subscription;

        if (!$sub) {
            return response()->json([
                'plan' => 'basic',
                'status' => 'trial',
                'trial_ends_at' => null,
                'current_period_end' => null,
                'days_remaining' => null,
                'trial_days_remaining' => null,
                'is_pro' => false,
                'is_active' => false,
            ]);
        }

        $trialDaysRemaining = $sub->trial_ends_at?->isFuture()
            ? (int) now()->diffInDays($sub->trial_ends_at, false)
            : null;
        $daysRemaining = $sub->current_period_end?->isFuture()
            ? (int) now()->diffInDays($sub->current_period_end, false)
            : null;

        return response()->json([
            'plan' => $sub->plan,
            'status' => $sub->status,
            'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
            'current_period_end' => $sub->current_period_end?->toIso8601String(),
            'days_remaining' => $daysRemaining,
            'trial_days_remaining' => $trialDaysRemaining,
            'is_pro' => $user->business->isOnProPlan(),
            'is_active' => $sub->isActive(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('business.subscription');
        $sub = $user->business->subscription;

        if (!$sub) {
            return response()->json(['message' => 'No subscription found.'], 404);
        }

        $plan = $request->input('plan');
        if (!in_array($plan, ['basic', 'pro', 'enterprise'], true)) {
            return response()->json(['message' => 'Invalid plan.'], 422);
        }

        $sub->update(['plan' => $plan]);

        return response()->json([
            'plan' => $sub->plan,
            'status' => $sub->status,
            'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
            'current_period_end' => $sub->current_period_end?->toIso8601String(),
            'is_pro' => $user->business->fresh()->isOnProPlan(),
            'is_active' => $sub->fresh()->isActive(),
        ]);
    }
}
