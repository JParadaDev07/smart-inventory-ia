<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_show_requires_auth(): void
    {
        $response = $this->getJson('/api/subscription');
        $response->assertUnauthorized();
    }

    public function test_subscription_show_returns_plan_and_status(): void
    {
        $business = Business::create(['name' => 'Test']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'User',
            'email' => 'u@example.com',
            'password' => Hash::make('password'),
        ]);
        $business->update(['owner_user_id' => $user->id]);
        Subscription::create([
            'business_id' => $business->id,
            'plan' => 'pro',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/subscription');

        $response->assertOk()
            ->assertJsonPath('plan', 'pro')
            ->assertJsonPath('status', 'trial')
            ->assertJsonPath('is_active', true)
            ->assertJsonStructure([
                'plan', 'status', 'trial_ends_at', 'current_period_end',
                'days_remaining', 'trial_days_remaining', 'is_pro', 'is_active',
            ]);
    }

    public function test_products_blocked_when_subscription_expired(): void
    {
        $business = Business::create(['name' => 'Test']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'User',
            'email' => 'u2@example.com',
            'password' => Hash::make('password'),
        ]);
        $business->update(['owner_user_id' => $user->id]);
        Subscription::create([
            'business_id' => $business->id,
            'plan' => 'basic',
            'status' => 'expired',
            'trial_ends_at' => now()->subDay(),
            'current_period_end' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/products');

        $response->assertForbidden()
            ->assertJsonPath('code', 'SUBSCRIPTION_EXPIRED')
            ->assertJsonFragment(['message' => 'Your subscription has expired. Please renew.']);
    }

    public function test_products_allowed_when_trial_active(): void
    {
        $business = Business::create(['name' => 'Test']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'User',
            'email' => 'u3@example.com',
            'password' => Hash::make('password'),
        ]);
        $business->update(['owner_user_id' => $user->id]);
        Subscription::create([
            'business_id' => $business->id,
            'plan' => 'basic',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/products');

        $response->assertOk();
    }
}
