<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    public function test_business_pages_render_for_owner(): void
    {
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);
        $this->actingAs($s['owner']);

        $program = $s['program'];
        $customer = $card->customer;
        foreach ([
            route('app.dashboard'), route('app.subscription'), route('app.business'), route('app.staff'),
            route('app.programs'), route('app.programs.create'), route('app.programs.edit', $program),
            route('app.customers'), route('app.customers.show', $card->id), route('app.scan'),
            route('app.history'), route('app.reports'),
        ] as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->getStatusCode(), "GET {$url} => {$res->getStatusCode()}");
        }
    }

    public function test_admin_pages_render_for_superadmin(): void
    {
        $s = $this->makeBusiness();
        $this->actingAs($this->makeUser('root@t.test', true));

        foreach ([
            route('admin.dashboard'), route('admin.businesses'), route('admin.businesses.create'),
            route('admin.businesses.show', $s['business']), route('admin.users'), route('admin.wallet'),
            route('admin.events'), route('admin.audit'), route('admin.system'),
        ] as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->getStatusCode(), "GET {$url} => {$res->getStatusCode()}");
        }
    }

    public function test_public_pages_render(): void
    {
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);

        $this->get('/negocio/'.$s['business']->slug)->assertOk()->assertSee('Obtener mi tarjeta');
        $this->get('/tarjeta/'.$card->uuid)->assertOk();
        $this->get('/tarjeta/'.$card->uuid.'/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get('/tarjeta/'.$card->uuid.'/strip-1.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/manifest.webmanifest')->assertOk();
        $this->get('/login')->assertOk();
    }
}
