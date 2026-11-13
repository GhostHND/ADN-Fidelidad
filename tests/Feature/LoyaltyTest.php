<?php

namespace Tests\Feature;

use App\Models\CustomerCard;
use App\Models\LoyaltyProgram;
use App\Models\RewardEntitlement;
use App\Models\StampTransaction;
use App\Services\LoyaltyException;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    private function svc(): LoyaltyService
    {
        return app(LoyaltyService::class);
    }

    public function test_award_is_idempotent(): void
    {
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);

        $a = $this->svc()->award($card, $s['cashier'], null, ['quantity' => 1], 'key-1');
        $b = $this->svc()->award($card, $s['cashier'], null, ['quantity' => 1], 'key-1');

        $this->assertFalse($a['duplicate']);
        $this->assertTrue($b['duplicate']);
        $this->assertSame(1, $card->fresh()->stamps_balance);
        $this->assertSame(1, StampTransaction::withoutGlobalScopes()->where('type', 'earn')->count());
    }

    public function test_balance_equals_ledger_and_limit_per_operation_is_enforced(): void
    {
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);

        $this->expectException(LoyaltyException::class);
        try {
            $this->svc()->award($card, $s['cashier'], null, ['quantity' => 9], 'k-big');
        } finally {
            $this->assertSame(0, $card->fresh()->stamps_balance);
        }
    }

    public function test_reward_unlocks_and_cannot_be_redeemed_twice(): void
    {
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);
        $this->svc()->award($card, $s['cashier'], null, ['quantity' => 3], 'k1');
        $r = $this->svc()->award($card, $s['cashier'], null, ['quantity' => 1], 'k2');

        $ent = RewardEntitlement::withoutGlobalScopes()->where('card_id', $card->id)->where('status', 'available')->firstOrFail();
        $this->svc()->redeem($ent, $s['cashier'], null);

        try {
            $this->svc()->redeem($ent->fresh(), $s['cashier'], null);
            $this->fail('El segundo canje debió fallar.');
        } catch (LoyaltyException $e) {
            $this->assertSame(409, $e->status);
        }
        $this->assertSame(0, (int) $card->fresh()->stamps_balance, 'El ciclo "reset" consume los sellos.');
        $this->assertSame(
            (int) StampTransaction::withoutGlobalScopes()->where('card_id', $card->id)->sum('stamps'),
            (int) $card->fresh()->stamps_balance
        );
    }

    public function test_reverse_restores_balance_once(): void
    {
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);
        $tx = $this->svc()->award($card, $s['cashier'], null, ['quantity' => 2], 'k1')['transaction'];

        $this->svc()->reverse($tx, $s['manager'], 'Error de captura');
        $this->assertSame(0, $card->fresh()->stamps_balance);

        $this->expectException(LoyaltyException::class);
        $this->svc()->reverse($tx->fresh(), $s['manager'], 'otra vez');
    }

    public function test_ledger_is_immutable(): void
    {
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);
        $tx = $this->svc()->award($card, $s['cashier'], null, ['quantity' => 1], 'k1')['transaction'];

        $this->expectException(\Throwable::class);
        $tx->update(['stamps' => 99]);
    }

    public function test_amount_rule_is_calculated_on_server(): void
    {
        $s = $this->makeBusiness();
        $s['program']->update(['rule_type' => 'per_amount', 'amount_per_stamp' => 100]);
        $card = $this->makeCard($s['business'], LoyaltyProgram::withoutGlobalScopes()->find($s['program']->id));

        $r = $this->svc()->award($card, $s['cashier'], null, ['amount' => 250, 'quantity' => 99], 'k-amt');
        $this->assertSame(2, $r['transaction']->stamps);
    }

    public function test_qr_from_other_business_is_forbidden(): void
    {
        $a = $this->makeBusiness('Negocio A');
        $b = $this->makeBusiness('Negocio B');
        $card = $this->makeCard($b['business'], $b['program']);

        try {
            $this->svc()->findCardByQr($a['business'], 'ADNF:'.$card->qr_token);
            $this->fail('Debió rechazar el QR de otro negocio.');
        } catch (LoyaltyException $e) {
            $this->assertSame(403, $e->status);
        }
        $this->assertSame($card->id, $this->svc()->findCardByQr($b['business'], 'ADNF:'.$card->qr_token)->id);
    }

    public function test_cycle_continue_policy_consumes_on_redeem_only(): void
    {
        $s = $this->makeBusiness();
        $s['program']->update(['cycle_policy' => 'continue']);
        $card = $this->makeCard($s['business'], LoyaltyProgram::withoutGlobalScopes()->find($s['program']->id));
        $this->svc()->award($card, $s['cashier'], null, ['quantity' => 3], 'k1');
        $this->svc()->award($card, $s['cashier'], null, ['quantity' => 1], 'k2');
        $this->assertSame(4, CustomerCard::withoutGlobalScopes()->find($card->id)->stamps_balance);

        $ent = RewardEntitlement::withoutGlobalScopes()->where('card_id', $card->id)->firstOrFail();
        $this->svc()->redeem($ent, $s['cashier'], null);

        $this->assertSame(0, CustomerCard::withoutGlobalScopes()->find($card->id)->stamps_balance);
    }
}
