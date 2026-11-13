<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class OnboardingGuideTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    public function test_guide_is_remembered_per_account_and_can_still_be_reopened(): void
    {
        $s = $this->makeBusiness();
        $this->actingAs($s['owner']);

        $this->get(route('app.scan'))->assertOk()->assertSee('Asigna sellos y canjea recompensas')->assertSee('\u0022seen\u0022:false', false);
        $this->postJson(route('app.onboarding.store', 'scan'))->assertOk()->assertJsonPath('ok', true);
        $this->postJson(route('app.onboarding.store', 'scan'))->assertOk();
        $this->get(route('app.scan'))->assertOk()->assertSee('\u0022seen\u0022:true', false)->assertSee('Ayuda');

        $this->assertDatabaseCount('onboarding_progress', 1);
        $this->assertDatabaseHas('onboarding_progress', ['user_id' => $s['owner']->id, 'business_id' => $s['business']->id, 'guide' => 'scan']);
        $this->actingAs($s['cashier'])->get(route('app.scan'))->assertOk()->assertSee('\u0022seen\u0022:false', false);
    }

    public function test_progress_is_separate_for_each_business(): void
    {
        $a = $this->makeBusiness('Uno');
        $b = $this->makeBusiness('Dos');
        $b['business']->users()->attach($a['owner']->id, ['role' => 'owner', 'is_active' => true]);
        $this->actingAs($a['owner'])->withSession(['business_id' => $a['business']->id]);
        $this->postJson(route('app.onboarding.store', 'scan'))->assertOk();

        $this->withSession(['business_id' => $b['business']->id])
            ->get(route('app.scan'))->assertOk()->assertSee('\u0022seen\u0022:false', false);
        $this->postJson(route('app.onboarding.store', 'scan'))->assertOk();

        $this->assertSame(2, DB::table('onboarding_progress')->where('user_id', $a['owner']->id)->count());
    }

    public function test_cashier_cannot_mark_management_or_admin_guides(): void
    {
        $s = $this->makeBusiness();
        $this->actingAs($s['cashier']);

        $this->postJson(route('app.onboarding.store', 'program-create'))->assertNotFound();
        $this->postJson(route('app.onboarding.store', 'unknown'))->assertNotFound();
        $this->postJson(route('admin.onboarding.store', 'admin-business'))->assertForbidden();

        $this->assertDatabaseCount('onboarding_progress', 0);
    }

    public function test_guest_cannot_save_progress(): void
    {
        $this->postJson(route('app.onboarding.store', 'scan'))->assertUnauthorized();
        $this->assertDatabaseCount('onboarding_progress', 0);
    }

    public function test_owner_sees_a_contextual_guide_in_each_supported_section(): void
    {
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);
        $this->actingAs($s['owner']);

        foreach ([
            [route('app.dashboard'), 'Bienvenido a tu panel'],
            [route('app.business'), 'Configura tu negocio'],
            [route('app.programs'), 'Crea tu tarjeta de fidelidad'],
            [route('app.programs.create'), 'Crea tu tarjeta de fidelidad'],
            [route('app.programs.edit', $s['program']), 'Configura y activa las recompensas'],
            [route('app.customers'), 'Registra y consulta clientes'],
            [route('app.customers.show', $card), 'Registra y consulta clientes'],
            [route('app.scan'), 'Asigna sellos y canjea recompensas'],
        ] as [$url, $title]) {
            $this->get($url)->assertOk()->assertSee($title);
        }

        $this->get(route('app.history'))->assertOk()->assertDontSee('Guía de primer uso');
    }

    public function test_superadmin_has_separate_administration_and_business_progress(): void
    {
        $s = $this->makeBusiness();
        $admin = $this->makeUser('root@t.test', true);
        $this->actingAs($admin);

        $this->get(route('admin.businesses.create'))->assertOk()->assertSee('Crear y administrar un negocio');
        $this->postJson(route('admin.onboarding.store', 'admin-business'))->assertOk();
        $this->withSession(['impersonate_business_id' => $s['business']->id])
            ->get(route('app.scan'))->assertOk()->assertSee('\u0022seen\u0022:false', false);
        $this->postJson(route('app.onboarding.store', 'scan'))->assertOk();

        $this->assertDatabaseHas('onboarding_progress', ['user_id' => $admin->id, 'scope_key' => 'admin', 'business_id' => null]);
        $this->assertDatabaseHas('onboarding_progress', ['user_id' => $admin->id, 'business_id' => $s['business']->id, 'guide' => 'scan']);
    }
}
