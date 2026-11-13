<?php

namespace Tests\Feature;

use App\Models\LoyaltyProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class TenancyAndPermissionsTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    public function test_cannot_open_program_of_other_business(): void
    {
        $a = $this->makeBusiness('Negocio A');
        $b = $this->makeBusiness('Negocio B');

        $this->actingAs($a['owner'])->get(route('app.programs.edit', $b['program']))->assertNotFound();
        $this->actingAs($a['owner'])->put(route('app.programs.update', $b['program']), ['name' => 'Hack'])->assertNotFound();
        $this->assertNotSame('Hack', LoyaltyProgram::withoutGlobalScopes()->find($b['program']->id)->name);
    }

    public function test_cannot_open_customer_card_of_other_business(): void
    {
        $a = $this->makeBusiness('Negocio A');
        $b = $this->makeBusiness('Negocio B');
        $card = $this->makeCard($b['business'], $b['program']);

        $this->actingAs($a['owner'])->get(route('app.customers.show', $card->id))->assertNotFound();
    }

    public function test_scan_lookup_rejects_other_business_qr(): void
    {
        $a = $this->makeBusiness('Negocio A');
        $b = $this->makeBusiness('Negocio B');
        $card = $this->makeCard($b['business'], $b['program']);

        $this->actingAs($a['cashier'])->postJson(route('app.scan.lookup'), ['code' => 'ADNF:'.$card->qr_token])
            ->assertStatus(403)->assertJson(['message' => 'Esta tarjeta pertenece a otro negocio.']);
    }

    public function test_cashier_scan_flow_end_to_end(): void
    {
        $a = $this->makeBusiness('Negocio A');
        $card = $this->makeCard($a['business'], $a['program']);
        $this->actingAs($a['cashier']);

        $this->postJson(route('app.scan.lookup'), ['code' => 'ADNF:'.$card->qr_token])->assertOk()->assertJsonPath('card.token', $card->uuid);
        $body = ['card' => $card->uuid, 'idempotency_key' => 'abcdefgh-1', 'quantity' => 2];
        $this->postJson(route('app.scan.award'), $body)->assertOk()->assertJsonPath('card.stamps', 2);
        $this->postJson(route('app.scan.award'), $body)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('card.stamps', 2);
    }

    public function test_cashier_cannot_access_management_pages(): void
    {
        $a = $this->makeBusiness('Negocio A');
        $this->actingAs($a['cashier']);

        $this->get(route('app.programs'))->assertForbidden();
        $this->get(route('app.staff'))->assertForbidden();
        $this->get(route('app.customers'))->assertForbidden();
        $this->get(route('app.scan'))->assertOk();
    }

    public function test_business_user_cannot_enter_admin(): void
    {
        $a = $this->makeBusiness('Negocio A');
        $this->actingAs($a['owner'])->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('app.dashboard'))->assertRedirect('/login');
        $this->get(route('admin.dashboard'))->assertRedirect('/login');
    }

    public function test_blocked_subscription_prevents_scanning(): void
    {
        $a = $this->makeBusiness('Negocio A', 'suspended');
        $card = $this->makeCard($a['business'], $a['program']);

        $this->actingAs($a['cashier'])->postJson(route('app.scan.award'), ['card' => $card->uuid, 'idempotency_key' => 'abcdefgh-2', 'quantity' => 1])->assertStatus(402);
        $this->assertSame(0, $card->fresh()->stamps_balance);
    }

    public function test_expired_subscription_is_read_only_but_data_visible(): void
    {
        $a = $this->makeBusiness('Negocio A', 'expired');
        $this->actingAs($a['owner'])->get(route('app.customers'))->assertOk();
        $this->actingAs($a['owner'])->post(route('app.programs.status', $a['program']), ['status' => 'paused'])->assertSessionHas('error');
        $this->assertSame('active', LoyaltyProgram::withoutGlobalScopes()->find($a['program']->id)->status);
    }

    public function test_login_throttle_and_inactive_user(): void
    {
        $a = $this->makeBusiness('Negocio A');
        $a['cashier']->update(['is_active' => false]);
        $this->post('/login', ['email' => $a['cashier']->email, 'password' => 'Secret-Password-1'])->assertSessionHasErrors();
        $this->assertGuest();

        $this->post('/login', ['email' => $a['owner']->email, 'password' => 'Secret-Password-1'])->assertRedirect();
        $this->assertAuthenticatedAs($a['owner']);
    }
}
