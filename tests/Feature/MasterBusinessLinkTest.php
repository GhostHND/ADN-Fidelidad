<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class MasterBusinessLinkTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    private const SECRET = 'a-test-secret-at-least-32-bytes-long';

    public function test_linking_existing_business_preserves_access_and_is_idempotent(): void
    {
        $scenario = $this->makeBusiness('GUZA');
        $business = $scenario['business'];
        $business->entitlement->update(['event_version' => 0]);
        $uuid = (string) Str::uuid();
        $payload = ['business_uuid' => $business->uuid, 'master_business_id' => $uuid];

        $this->signed('/api/v1/master/businesses/search', ['query' => 'GUZA'])
            ->assertOk()->assertJsonPath('data.0.uuid', $business->uuid);
        $this->signed('/api/v1/master/businesses/link', $payload)
            ->assertOk()->assertJsonPath('data.master_business_id', $uuid);
        $this->signed('/api/v1/master/businesses/link', $payload)->assertOk();

        $this->assertSame('active', $business->fresh()->entitlement->status);
        $this->assertSame(3, $business->users()->count());
        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'master_business_id' => $uuid]);
    }

    public function test_rebinding_and_prior_sync_are_rejected(): void
    {
        $first = $this->makeBusiness('First')['business'];
        $second = $this->makeBusiness('Second')['business'];
        $first->entitlement->update(['event_version' => 0]);
        $second->entitlement->update(['event_version' => 0]);
        $id = (string) Str::uuid();
        $this->signed('/api/v1/master/businesses/link', ['business_uuid' => $first->uuid, 'master_business_id' => $id])->assertOk();
        $this->signed('/api/v1/master/businesses/link', ['business_uuid' => $first->uuid, 'master_business_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->signed('/api/v1/master/businesses/link', ['business_uuid' => $second->uuid, 'master_business_id' => $id])->assertUnprocessable();
        $second->entitlement->update(['event_version' => 3]);
        $this->signed('/api/v1/master/businesses/link', ['business_uuid' => $second->uuid, 'master_business_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertNull($second->fresh()->master_business_id);
    }

    public function test_signature_is_bound_to_path_and_replay_is_rejected(): void
    {
        $business = $this->makeBusiness('GUZA')['business'];
        $business->entitlement->update(['event_version' => 0]);
        $payload = ['business_uuid' => $business->uuid, 'master_business_id' => (string) Str::uuid()];
        $this->signed('/api/v1/master/businesses/link', $payload, '/api/v1/master/businesses/search')->assertUnauthorized();
        $key = (string) Str::uuid();
        $this->signed('/api/v1/master/businesses/link', $payload, null, $key)->assertOk();
        $this->signed('/api/v1/master/businesses/link', $payload, null, $key)->assertStatus(409);
    }

    private function signed(string $path, array $payload, ?string $signedPath = null, ?string $key = null)
    {
        config(['fidelidad.master.link_secret' => self::SECRET]);
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $key ??= (string) Str::uuid();
        $signature = hash_hmac('sha256', "POST\n".($signedPath ?? $path)."\n{$timestamp}\n{$key}\n{$body}", self::SECRET);

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ADN_TIMESTAMP' => $timestamp,
            'HTTP_X_ADN_IDEMPOTENCY_KEY' => $key,
            'HTTP_X_ADN_SIGNATURE' => $signature,
        ], $body);
    }
}
