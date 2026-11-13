<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class PublicRegistrationTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    public function test_registration_requires_consent_and_contact(): void
    {
        $s = $this->makeBusiness();
        $url = '/negocio/'.$s['business']->slug.'/registro';

        $this->post($url, ['name' => 'Ana', 'email' => 'ana@t.test'])->assertSessionHasErrors('consent_privacy');
        $this->post($url, ['name' => 'Ana', 'consent_privacy' => 1])->assertSessionHasErrors();
        $this->assertSame(0, Customer::count());
    }

    public function test_registration_creates_card_and_redirects(): void
    {
        $s = $this->makeBusiness();
        $res = $this->post('/negocio/'.$s['business']->slug.'/registro', ['name' => 'Ana', 'email' => 'Ana@T.test', 'consent_privacy' => 1]);

        $card = CustomerCard::withoutGlobalScopes()->firstOrFail();
        $res->assertRedirect(route('public.card', $card->uuid));
        $this->assertSame('ana@t.test', Customer::first()->email);
    }

    public function test_existing_customer_does_not_receive_card_link(): void
    {
        $s = $this->makeBusiness();
        $url = '/negocio/'.$s['business']->slug.'/registro';
        $this->post($url, ['name' => 'Ana', 'email' => 'ana@t.test', 'consent_privacy' => 1]);

        $res = $this->post($url, ['name' => 'Impostor', 'email' => 'ana@t.test', 'consent_privacy' => 1]);
        $res->assertSessionHas('error');
        $this->assertSame(1, CustomerCard::withoutGlobalScopes()->count());
    }

    public function test_registration_blocked_when_subscription_not_full(): void
    {
        $s = $this->makeBusiness('Negocio X', 'expired');
        $this->post('/negocio/'.$s['business']->slug.'/registro', ['name' => 'Ana', 'email' => 'a@t.test', 'consent_privacy' => 1])->assertNotFound();
    }

    public function test_anonymize_removes_personal_data_but_keeps_ledger(): void
    {
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);
        app(\App\Services\LoyaltyService::class)->award($card, $s['cashier'], null, ['quantity' => 1], 'k1');

        $this->post('/tarjeta/'.$card->uuid.'/privacidad', [])->assertSessionHasErrors('confirm');
        $this->post('/tarjeta/'.$card->uuid.'/privacidad', ['confirm' => 1])->assertRedirect();

        $c = Customer::find($card->customer_id);
        $this->assertNull($c->email);
        $this->assertNotNull($c->anonymized_at);
        $this->assertSame(1, $card->fresh()->stamps_balance);
    }

    public function test_invalid_card_uuid_is_404(): void
    {
        $this->get('/tarjeta/not-a-uuid')->assertNotFound();
        $this->get('/tarjeta/'.\Illuminate\Support\Str::uuid())->assertNotFound();
    }
}
