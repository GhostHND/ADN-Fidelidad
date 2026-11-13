<?php

namespace Tests\Feature;

use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class PublicCardShortcutTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    public function test_card_explains_the_iphone_shortcut_when_wallets_are_unavailable(): void
    {
        config([
            'fidelidad.wallet.apple.enabled' => false,
            'fidelidad.wallet.google.issuer_id' => null,
        ]);
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);

        $response = $this->get(route('public.card', $card->uuid));

        $response->assertOk()
            ->assertSee('Apple Wallet está en desarrollo')
            ->assertSee('Agregar a pantalla de inicio')
            ->assertSee('No es un pase de Apple Wallet')
            ->assertDontSee('Añadir a Apple Wallet')
            ->assertDontSee('Añadir a Google Wallet')
            ->assertDontSee('rel="manifest"', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_card_remains_available_with_updated_balance_and_reward(): void
    {
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $loyalty = app(LoyaltyService::class);
        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 3], 'shortcut-first');
        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'shortcut-last');

        $this->get(route('public.card', $card->uuid))
            ->assertOk()->assertSee('0 de 4')->assertSee('¡Tienes recompensas listas!')
            ->assertSee('Premio')->assertSee('Código QR de tu tarjeta');
    }

    public function test_apple_download_is_disabled_even_if_credentials_exist(): void
    {
        config([
            'fidelidad.wallet.apple.enabled' => false,
            'fidelidad.wallet.apple.pass_type_id' => 'pass.example',
            'fidelidad.wallet.apple.team_id' => 'TEAM123',
            'fidelidad.wallet.apple.certificate_path' => __FILE__,
            'fidelidad.wallet.apple.certificate_password' => 'example',
            'fidelidad.wallet.apple.wwdr_path' => __FILE__,
        ]);
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);

        $this->get('/tarjeta/'.$card->uuid.'/apple.pkpass')->assertNotFound();
        $this->get(route('public.card', $card->uuid))->assertOk()->assertSee('Apple Wallet está en desarrollo')
            ->assertDontSee('Añadir a Apple Wallet');
    }

    public function test_google_wallet_remains_available_when_apple_wallet_is_disabled(): void
    {
        config([
            'fidelidad.wallet.apple.enabled' => false,
            'fidelidad.wallet.google.issuer_id' => '123456789',
            'fidelidad.wallet.google.service_account_json' => __FILE__,
        ]);
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);

        $this->get(route('public.card', $card->uuid))
            ->assertOk()->assertSee('Añadir a Google Wallet')->assertSee('Apple Wallet está en desarrollo')
            ->assertSee('Guardar esta tarjeta en iPhone');
    }

    public function test_apple_wallet_can_be_enabled_later_with_valid_configuration(): void
    {
        config([
            'fidelidad.wallet.apple.enabled' => true,
            'fidelidad.wallet.apple.pass_type_id' => 'pass.example',
            'fidelidad.wallet.apple.team_id' => 'TEAM123',
            'fidelidad.wallet.apple.certificate_path' => __FILE__,
            'fidelidad.wallet.apple.certificate_password' => 'example',
            'fidelidad.wallet.apple.wwdr_path' => __FILE__,
        ]);
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);

        $this->get(route('public.card', $card->uuid))->assertOk()
            ->assertSee('Añadir a Apple Wallet')->assertDontSee('Apple Wallet está en desarrollo');
    }

    public function test_anonymized_card_does_not_offer_a_shortcut(): void
    {
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $card->customer->update(['anonymized_at' => now()]);

        $this->get(route('public.card', $card->uuid))->assertOk()->assertDontSee('Agregar a pantalla de inicio');
    }
}
