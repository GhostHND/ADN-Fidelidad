<?php

namespace Tests\Feature;

use App\Models\RewardEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class ScanRewardAlertTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    public function test_awarding_final_stamp_reports_a_new_reward_and_keeps_it_available_after_reset(): void
    {
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $this->actingAs($scenario['cashier']);
        $this->postJson(route('app.scan.award'), ['card' => $card->uuid, 'idempotency_key' => 'first-three', 'quantity' => 3])
            ->assertOk()->assertJsonPath('new_entitlements', 0);

        $this->postJson(route('app.scan.award'), ['card' => $card->uuid, 'idempotency_key' => 'final-stamp', 'quantity' => 1])
            ->assertOk()
            ->assertJsonPath('new_entitlements', 1)
            ->assertJsonPath('card.stamps', 0)
            ->assertJsonPath('card.rewards.0.name', 'Premio');

        $this->assertSame(1, RewardEntitlement::withoutGlobalScopes()->where('card_id', $card->id)->where('status', 'available')->count());
    }

    public function test_repeated_operation_does_not_report_another_new_reward(): void
    {
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $this->actingAs($scenario['cashier']);
        $this->postJson(route('app.scan.award'), ['card' => $card->uuid, 'idempotency_key' => 'first-three', 'quantity' => 3])->assertOk();
        $body = ['card' => $card->uuid, 'idempotency_key' => 'final-stamp', 'quantity' => 1];
        $this->postJson(route('app.scan.award'), $body)->assertOk();

        $this->postJson(route('app.scan.award'), $body)
            ->assertOk()->assertJsonPath('duplicate', true)
            ->assertJsonPath('new_entitlements', 0)
            ->assertJsonPath('card.rewards.0.name', 'Premio');

        $this->assertSame(1, RewardEntitlement::withoutGlobalScopes()->where('card_id', $card->id)->count());
    }

    public function test_completing_a_cycle_with_an_expired_reward_reports_cycle_without_a_new_reward(): void
    {
        $scenario = $this->makeBusiness();
        $scenario['program']->rewards()->update(['valid_until' => now()->subDay()]);
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $this->actingAs($scenario['cashier']);
        $this->postJson(route('app.scan.award'), ['card' => $card->uuid, 'idempotency_key' => 'first-three', 'quantity' => 3])->assertOk();

        $this->postJson(route('app.scan.award'), ['card' => $card->uuid, 'idempotency_key' => 'final-stamp', 'quantity' => 1])
            ->assertOk()->assertJsonPath('new_entitlements', 0)
            ->assertJsonPath('cycle_completed', true)
            ->assertJsonPath('card.rewards', []);

        $this->assertSame(0, RewardEntitlement::withoutGlobalScopes()->where('card_id', $card->id)->count());
    }
}
