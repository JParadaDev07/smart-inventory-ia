<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\WompiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WompiWebhookTest extends TestCase
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

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(false);
        });

        $response = $this->postJson('/api/webhooks/wompi', [
            'event' => 'transaction.updated',
            'data' => [
                'transaction' => [
                    'id' => 'tx-123',
                    'reference' => 'some-ref',
                    'status' => 'APPROVED',
                    'amount_in_cents' => 7990000,
                ],
            ],
            'signature' => ['checksum' => 'x', 'timestamp' => '1', 'properties' => []],
        ]);

        $response->assertUnauthorized()
            ->assertJsonFragment(['message' => 'Invalid signature']);
    }

    public function test_webhook_returns_200_for_unknown_event(): void
    {
        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
        });

        $response = $this->postJson('/api/webhooks/wompi', [
            'event' => 'other.event',
            'data' => [],
            'signature' => ['checksum' => 'x', 'timestamp' => '1', 'properties' => []],
        ]);

        $response->assertOk();
    }

    public function test_webhook_returns_200_for_unknown_reference_when_signature_valid(): void
    {
        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
        });

        $response = $this->postJson('/api/webhooks/wompi', [
            'event' => 'transaction.updated',
            'data' => [
                'transaction' => [
                    'id' => 'tx-123',
                    'reference' => 'nonexistent-ref',
                    'status' => 'APPROVED',
                    'amount_in_cents' => 7990000,
                ],
            ],
            'signature' => ['checksum' => 'x', 'timestamp' => '1', 'properties' => []],
        ]);

        $response->assertOk();
    }

    public function test_webhook_approval_updates_payment_and_activates_subscription(): void
    {
        $reference = 'test-ref-' . uniqid();
        $amountInCents = 7990000; // 79_900 COP
        $transactionId = 'wompi-tx-' . uniqid();

        $payment = Payment::create([
            'business_id' => $this->user->business_id,
            'plan' => 'pro',
            'amount' => 79900,
            'amount_in_cents' => $amountInCents,
            'currency' => 'COP',
            'reference' => $reference,
            'status' => 'pending',
            'wompi_status' => 'PENDING',
            'raw_response' => null,
        ]);

        $transactionPayload = [
            'id' => $transactionId,
            'reference' => $reference,
            'status' => 'APPROVED',
            'amount_in_cents' => $amountInCents,
        ];

        $this->mock(WompiService::class, function ($mock) use ($transactionPayload, $transactionId) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
            $mock->shouldReceive('fetchTransaction')
                ->once()
                ->with($transactionId)
                ->andReturn($transactionPayload);
            $mock->shouldReceive('verifyTransactionApproved')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/api/webhooks/wompi', [
            'event' => 'transaction.updated',
            'data' => [
                'transaction' => array_merge($transactionPayload, ['extra' => 'ignored']),
            ],
            'signature' => ['checksum' => 'x', 'timestamp' => '1', 'properties' => []],
        ]);

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('APPROVED', $payment->wompi_status);
        $this->assertSame($transactionId, $payment->wompi_transaction_id);

        $sub = Subscription::where('business_id', $this->user->business_id)->first();
        $this->assertSame('pro', $sub->plan);
        $this->assertSame('active', $sub->status);
        $this->assertNotNull($sub->current_period_end);
        $this->assertTrue($sub->current_period_end->isFuture());
    }

    public function test_webhook_direct_payment_without_business_id_updates_status_only(): void
    {
        $reference = 'direct-ref-' . uniqid();
        $amountInCents = 100000; // 1_000 COP
        $transactionId = 'wompi-tx-direct';

        $payment = Payment::withoutGlobalScopes()->create([
            'business_id' => null,
            'plan' => null,
            'amount' => 1000,
            'amount_in_cents' => $amountInCents,
            'currency' => 'COP',
            'reference' => $reference,
            'status' => 'pending',
            'wompi_status' => 'PENDING',
            'raw_response' => null,
        ]);

        $transactionPayload = [
            'id' => $transactionId,
            'reference' => $reference,
            'status' => 'APPROVED',
            'amount_in_cents' => $amountInCents,
        ];

        $this->mock(WompiService::class, function ($mock) use ($transactionPayload, $transactionId) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
            $mock->shouldReceive('fetchTransaction')
                ->once()
                ->with($transactionId)
                ->andReturn($transactionPayload);
            $mock->shouldReceive('verifyTransactionApproved')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/api/webhooks/wompi', [
            'event' => 'transaction.updated',
            'data' => ['transaction' => $transactionPayload],
            'signature' => ['checksum' => 'x', 'timestamp' => '1', 'properties' => []],
        ]);

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('APPROVED', $payment->wompi_status);
        $this->assertNull($payment->business_id);
        $this->assertNull($payment->plan);

        $sub = Subscription::where('business_id', $this->user->business_id)->first();
        $this->assertSame('basic', $sub->plan);
        $this->assertSame('trial', $sub->status);
    }

    public function test_webhook_does_not_double_activate_subscription_if_already_approved(): void
    {
        $reference = 'test-ref-' . uniqid();
        $amountInCents = 7990000;
        $transactionId = 'wompi-tx-123';

        $payment = Payment::create([
            'business_id' => $this->user->business_id,
            'plan' => 'pro',
            'amount' => 79900,
            'amount_in_cents' => $amountInCents,
            'currency' => 'COP',
            'reference' => $reference,
            'status' => 'paid',
            'wompi_status' => 'APPROVED',
            'wompi_transaction_id' => $transactionId,
            'raw_response' => [],
        ]);

        Subscription::where('business_id', $this->user->business_id)->update([
            'plan' => 'pro',
            'status' => 'active',
            'current_period_end' => now()->addDays(30),
        ]);

        $this->mock(WompiService::class, function ($mock) use ($reference, $transactionId, $amountInCents) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
            $mock->shouldReceive('fetchTransaction')
                ->once()
                ->with($transactionId)
                ->andReturn([
                    'id' => $transactionId,
                    'reference' => $reference,
                    'status' => 'APPROVED',
                    'amount_in_cents' => $amountInCents,
                ]);
            $mock->shouldReceive('verifyTransactionApproved')->once()->andReturn(true);
        });

        $response = $this->postJson('/api/webhooks/wompi', [
            'event' => 'transaction.updated',
            'data' => [
                'transaction' => [
                    'id' => $transactionId,
                    'reference' => $reference,
                    'status' => 'APPROVED',
                    'amount_in_cents' => $amountInCents,
                ],
            ],
            'signature' => ['checksum' => 'x', 'timestamp' => '1', 'properties' => []],
        ]);

        $response->assertOk();

        $sub = Subscription::where('business_id', $this->user->business_id)->first();
        $originalEnd = $sub->current_period_end;
        $sub->refresh();
        $this->assertSame('pro', $sub->plan);
        $this->assertSame('active', $sub->status);
        $this->assertTrue($sub->current_period_end->equalTo($originalEnd), 'Period end should not change on duplicate webhook');
    }
}