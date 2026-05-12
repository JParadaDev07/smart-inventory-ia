<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $business = Business::create(['name' => 'Test Business']);
        $this->user = User::create([
            'business_id' => $business->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
        $business->update(['owner_user_id' => $this->user->id]);
        Subscription::create([
            'business_id' => $business->id,
            'plan' => 'basic',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function test_plans_endpoint_returns_public_plan_prices(): void
    {
        $response = $this->getJson('/api/plans');
        $response->assertOk()
            ->assertJsonStructure([
                'currency',
                'basic' => ['name', 'price'],
                'pro' => ['name', 'price'],
            ])
            ->assertJsonPath('currency', 'COP')
            ->assertJsonPath('basic.price', 29900)
            ->assertJsonPath('pro.price', 59900);
    }

    public function test_create_payment_requires_authentication(): void
    {
        $response = $this->postJson('/api/billing/create-payment', ['plan' => 'pro']);
        $response->assertUnauthorized();
    }

    public function test_create_payment_validates_plan(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/billing/create-payment', ['plan' => 'invalid']);
        $response->assertUnprocessable();
    }

    public function test_create_payment_returns_503_when_wompi_not_configured(): void
    {
        config(['wompi.public_key' => null, 'wompi.integrity_secret' => null]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/billing/create-payment', ['plan' => 'pro']);

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'Payment gateway is not configured. Please contact support.']);
    }

    public function test_create_payment_returns_checkout_url_when_configured(): void
    {
        config([
            'wompi.is_production' => false,
            'wompi.environment' => 'test',
            'wompi.public_key' => 'pub_test_fake',
            'wompi.integrity_secret' => 'prod_integrity_fake',
            'wompi.checkout_url' => 'https://checkout.wompi.co/p',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/billing/create-payment', ['plan' => 'pro']);

        $response->assertOk()
            ->assertJsonStructure(['checkout_url', 'reference', 'widget_params' => [
                'public_key', 'amount_in_cents', 'reference', 'signature_integrity', 'currency',
            ]])
            ->assertJsonFragment(['reference' => $response->json('reference')]);

        $checkoutUrl = $response->json('checkout_url');
        $this->assertStringContainsString('checkout.wompi.co', $checkoutUrl);
        $this->assertStringContainsString('public-key=pub_test_fake', $checkoutUrl);
        $this->assertStringContainsString('amount-in-cents=5990000', $checkoutUrl);
        $this->assertStringContainsString('currency=COP', $checkoutUrl);

        // Wompi requires params as query string (after ?), not in path
        $this->assertStringContainsString('?', $checkoutUrl, 'Checkout URL must use query string for Wompi');
        $parsed = parse_url($checkoutUrl);
        $this->assertArrayHasKey('query', $parsed, 'Checkout URL must have a query string');
        parse_str($parsed['query'], $query);
        $this->assertArrayHasKey('public-key', $query);
        $this->assertArrayHasKey('currency', $query);
        $this->assertArrayHasKey('amount-in-cents', $query);
        $this->assertArrayHasKey('reference', $query);
        $this->assertSame('COP', $query['currency']);
        $this->assertSame('5990000', $query['amount-in-cents']);

        $payment = Payment::where('business_id', $this->user->business_id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('pro', $payment->plan);
        $this->assertSame(59900, $payment->amount);
        $this->assertSame(5990000, $payment->amount_in_cents);
        $this->assertSame('COP', $payment->currency);
        $this->assertSame($response->json('reference'), $payment->reference);
    }

    public function test_create_payment_returns_503_in_production_with_localhost_redirect(): void
    {
        config([
            'app.env' => 'production',
            'wompi.is_production' => true,
            'wompi.environment' => 'production',
            'wompi.public_key' => 'pub_prod_fakekey123',
            'wompi.integrity_secret' => 'prod_integrity_fake',
            'wompi.redirect_url' => null,
            'app.frontend_url' => 'http://localhost:5173',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/billing/create-payment', ['plan' => 'pro']);

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'Production redirect URL must use HTTPS (or a valid tunnel URL).']);
    }

    public function test_create_payment_succeeds_in_production_with_https_redirect(): void
    {
        config([
            'wompi.is_production' => true,
            'wompi.environment' => 'production',
            'wompi.public_key' => 'pub_prod_fakekey123',
            'wompi.integrity_secret' => 'prod_integrity_fake',
            'wompi.checkout_url' => 'https://checkout.wompi.co/p',
            'wompi.redirect_url' => 'https://app.example.com/payment/return',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/billing/create-payment', ['plan' => 'pro']);

        $response->assertOk()
            ->assertJsonStructure(['checkout_url', 'reference']);
        $this->assertStringContainsString('pub_prod_fakekey123', $response->json('checkout_url'));
    }

    public function test_create_payment_basic_plan_uses_basic_price(): void
    {
        config([
            'wompi.is_production' => false,
            'wompi.environment' => 'test',
            'wompi.public_key' => 'pub_test_fake',
            'wompi.integrity_secret' => 'prod_integrity_fake',
            'wompi.checkout_url' => 'https://checkout.wompi.co/p',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/billing/create-payment', ['plan' => 'basic']);

        $response->assertOk();
        $this->assertStringContainsString('amount-in-cents=2990000', $response->json('checkout_url'));
        $payment = Payment::where('business_id', $this->user->business_id)->first();
        $this->assertSame(29900, $payment->amount);
        $this->assertSame(2990000, $payment->amount_in_cents);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/api/billing/webhook', [
            'event' => 'transaction.updated',
            'data' => ['transaction' => ['reference' => 'some-ref', 'status' => 'APPROVED']],
        ]);
        $response->assertUnauthorized()
            ->assertJsonFragment(['message' => 'Invalid signature']);
    }

    public function test_webhook_returns_200_for_unknown_reference_when_signature_valid(): void
    {
        $this->mock(\App\Services\Billing\WompiService::class, function ($mock) {
            $mock->shouldReceive('verifyEventSignature')->andReturn(true);
        });

        $response = $this->postJson('/api/billing/webhook', [
            'event' => 'transaction.updated',
            'data' => ['transaction' => ['reference' => 'nonexistent-ref', 'status' => 'APPROVED']],
            'signature' => ['checksum' => 'x', 'timestamp' => '1', 'properties' => []],
        ]);
        $response->assertOk();
    }

    public function test_webhook_approval_activates_subscription(): void
    {
        $payment = Payment::create([
            'business_id' => $this->user->business_id,
            'plan' => 'pro',
            'amount' => 79900,
            'amount_in_cents' => 7990000,
            'currency' => 'COP',
            'reference' => 'test-ref-'.uniqid(),
            'wompi_status' => 'PENDING',
            'raw_response' => [],
        ]);

        $this->mock(\App\Services\Billing\WompiService::class, function ($mock) {
            $mock->shouldReceive('verifyEventSignature')->andReturn(true);
        });

        $response = $this->postJson('/api/billing/webhook', [
            'event' => 'transaction.updated',
            'data' => [
                'transaction' => [
                    'id' => 'wompi-tx-123',
                    'reference' => $payment->reference,
                    'status' => 'APPROVED',
                ],
            ],
            'signature' => ['checksum' => 'x', 'timestamp' => '1', 'properties' => []],
        ]);
        $response->assertOk();

        $payment->refresh();
        $this->assertSame('APPROVED', $payment->wompi_status);
        $this->assertSame('wompi-tx-123', $payment->wompi_transaction_id);

        $sub = Subscription::where('business_id', $this->user->business_id)->first();
        $this->assertSame('pro', $sub->plan);
        $this->assertSame('active', $sub->status);
        $this->assertTrue($sub->current_period_end->isFuture());
    }
}
