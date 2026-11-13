<?php

namespace Tests\Support;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerCard;
use App\Models\LoyaltyProgram;
use App\Models\SubscriptionEntitlement;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Support\Facades\DB;

/** Construye escenarios multi-negocio para pruebas. */
trait BuildsScenario
{
    protected function makeUser(string $email, bool $super = false): User
    {
        $u = new User(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => 'Secret-Password-1', 'is_active' => true]);
        $u->is_superadmin = $super;
        $u->save();

        return $u;
    }

    /** @return array{business:Business, owner:User, manager:User, cashier:User, program:LoyaltyProgram} */
    protected function makeBusiness(string $name = 'Negocio A', string $status = 'active', ?array $features = null, array $limits = []): array
    {
        $slug = str($name)->slug();
        $business = Business::create(['name' => $name]);
        $owner = $this->makeUser("owner-{$slug}@t.test");
        $manager = $this->makeUser("manager-{$slug}@t.test");
        $cashier = $this->makeUser("cashier-{$slug}@t.test");
        $business->users()->attach([
            $owner->id => ['role' => 'owner', 'is_active' => true],
            $manager->id => ['role' => 'manager', 'is_active' => true],
            $cashier->id => ['role' => 'cashier', 'is_active' => true],
        ]);
        SubscriptionEntitlement::create([
            'business_id' => $business->id, 'status' => $status, 'plan' => 'test', 'subscription_id' => 's-'.$business->id,
            'features' => $features ?? config('fidelidad.plan_features'),
            'limits' => $limits + ['max_programs' => 10, 'max_branches' => 10, 'max_staff' => 10, 'max_customers' => 1000],
            'event_version' => 1,
        ]);
        $program = LoyaltyProgram::withoutGlobalScopes()->create([
            'business_id' => $business->id, 'name' => 'Programa '.$name, 'status' => 'active', 'stamps_goal' => 4,
            'rule_type' => 'per_purchase', 'max_stamps_per_operation' => 3, 'cycle_policy' => 'reset',
            'design' => ['background_color' => '#1F2937', 'foreground_color' => '#FFFFFF', 'label_color' => '#0FA7B4', 'card_title' => $name],
        ]);
        $program->rewards()->create(['business_id' => $business->id, 'name' => 'Premio', 'type' => 'free_product', 'stamps_required' => 4, 'consumes_stamps' => true, 'status' => 'active']);

        return compact('business', 'owner', 'manager', 'cashier', 'program');
    }

    protected function makeCard(Business $business, LoyaltyProgram $program, string $email = 'cli@t.test'): CustomerCard
    {
        $customer = Customer::create(['name' => 'Cliente', 'email' => $email]);
        DB::table('customer_business')->insert(['business_id' => $business->id, 'customer_id' => $customer->id, 'consent_privacy' => true, 'created_at' => now(), 'updated_at' => now()]);

        return app(LoyaltyService::class)->issueCard($business, $program, $customer);
    }
}
