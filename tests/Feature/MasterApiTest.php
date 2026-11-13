<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SubscriptionEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class MasterApiTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    private const SECRET = 'test-secret';

    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'event_id' => 'evt-'.uniqid(), 'event_type' => 'subscription.updated', 'event_version' => 5,
            'product_code' => 'adn-fidelidad', 'business' => ['master_business_id' => 'M-1'],
            'subscription' => ['id' => 'sub-1', 'plan' => 'pro', 'status' => 'active', 'starts_at' => null, 'ends_at' => null, 'features' => ['reports', 'wallet_google'], 'limits' => ['max_programs' => 3]],
        ], $override);
    }

    private function send(array $payload, ?string $secret = self::SECRET, ?int $ts = null, ?string $key = null)
    {
        config(['fidelidad.master.webhook_secret' => self::SECRET]);
        $body = json_encode($payload);
        $ts ??= now()->timestamp;
        $key ??= 'idem-'.uniqid();
        $sig = hash_hmac('sha256', $ts."\n".$key."\n".$body, (string) $secret);

        return $this->call('POST', '/api/v1/master/entitlements', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ADN_TIMESTAMP' => (string) $ts, 'HTTP_X_ADN_IDEMPOTENCY_KEY' => $key, 'HTTP_X_ADN_SIGNATURE' => $sig,
        ], $body);
    }

    private function setupBusiness(): \App\Models\Business
    {
        $s = $this->makeBusiness('Negocio M', 'trial');
        $s['business']->update(['master_business_id' => 'M-1']);
        $s['business']->entitlement->update(['event_version' => 1]);

        return $s['business'];
    }

    public function test_valid_event_updates_entitlement(): void
    {
        $b = $this->setupBusiness();
        $this->send($this->payload())->assertOk();

        $e = SubscriptionEntitlement::where('business_id', $b->id)->first();
        $this->assertSame('active', $e->status);
        $this->assertSame('pro', $e->plan);
        $this->assertSame(['reports', 'wallet_google'], $e->features);
        $this->assertSame(5, (int) $e->event_version);
    }

    public function test_bad_signature_is_rejected_and_nothing_changes(): void
    {
        $b = $this->setupBusiness();
        $this->send($this->payload(), 'otro-secreto')->assertStatus(401);
        $this->assertSame('trial', SubscriptionEntitlement::where('business_id', $b->id)->first()->status);
    }

    public function test_expired_timestamp_is_rejected(): void
    {
        $this->setupBusiness();
        $this->send($this->payload(), self::SECRET, now()->subHour()->timestamp)->assertStatus(401);
    }

    public function test_replay_of_same_signed_request_is_rejected(): void
    {
        $this->setupBusiness();
        $p = $this->payload();
        $ts = now()->timestamp;
        $this->send($p, self::SECRET, $ts, 'same-key')->assertOk();
        $this->send($p, self::SECRET, $ts, 'same-key')->assertStatus(409);
    }

    public function test_same_event_id_is_idempotent_and_stale_version_ignored(): void
    {
        $b = $this->setupBusiness();
        $p = $this->payload(['event_id' => 'evt-fixed']);
        $this->send($p)->assertOk();
        $this->send($p)->assertOk()->assertJsonPath('status', 'duplicate');

        $stale = $this->payload(['event_version' => 2, 'subscription' => ['status' => 'suspended']]);
        $this->send($stale)->assertOk()->assertJsonPath('status', 'ignored');
        $this->assertSame('active', SubscriptionEntitlement::where('business_id', $b->id)->first()->status);
    }

    public function test_unknown_business_and_invalid_payload(): void
    {
        $this->setupBusiness();
        $this->send($this->payload(['business' => ['master_business_id' => 'NOPE']]))->assertStatus(404);
        $this->send($this->payload(['subscription' => ['status' => 'raro']]))->assertStatus(422);
    }

    public function test_suspension_from_master_blocks_business(): void
    {
        $b = $this->setupBusiness();
        $this->send($this->payload(['subscription' => ['status' => 'suspended']]))->assertOk();
        $this->assertSame('blocked', app(\App\Services\SubscriptionService::class)->mode($b->fresh()));
    }
}
