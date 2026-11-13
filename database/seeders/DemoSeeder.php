<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\LoyaltyProgram;
use App\Models\SubscriptionEntitlement;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración SOLO para entornos locales. No ejecutar en producción.
 * Uso: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder no se ejecuta en producción.');

            return;
        }

        $mk = function (string $email, string $name, bool $super = false) {
            $u = User::firstOrNew(['email' => $email]);
            $u->name = $name;
            $u->password = 'Demo-Password-2026';
            $u->is_active = true;
            $u->is_superadmin = $super;
            $u->save();

            return $u;
        };

        $mk('admin@demo.test', 'Superadmin Demo', true);
        $owner = $mk('dueno@demo.test', 'Dueño Demo');
        $manager = $mk('gerente@demo.test', 'Gerente Demo');
        $cashier = $mk('cajero@demo.test', 'Cajero Demo');

        $business = Business::firstOrCreate(['slug' => 'cafe-demo'], ['name' => 'Café Demo', 'description' => 'Negocio de demostración.', 'master_business_id' => 'demo-master-1']);
        $branch = $business->branches()->firstOrCreate(['name' => 'Sucursal Centro']);

        $business->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'is_active' => true],
            $manager->id => ['role' => 'manager', 'is_active' => true],
            $cashier->id => ['role' => 'cashier', 'is_active' => true, 'branch_id' => $branch->id],
        ]);

        SubscriptionEntitlement::updateOrCreate(['business_id' => $business->id], [
            'plan' => 'demo', 'status' => 'active', 'subscription_id' => 'demo', 'event_version' => 0,
            'features' => config('fidelidad.plan_features'),
            'limits' => ['max_programs' => 5, 'max_branches' => 5, 'max_staff' => 10, 'max_customers' => 5000],
        ]);

        if (! LoyaltyProgram::withoutGlobalScopes()->where('business_id', $business->id)->exists()) {
            $program = LoyaltyProgram::withoutGlobalScopes()->create([
                'business_id' => $business->id, 'name' => 'Café frecuente', 'status' => 'active', 'stamps_goal' => 8,
                'rule_type' => 'per_purchase', 'max_stamps_per_operation' => 3, 'cycle_policy' => 'reset', 'published_at' => now(),
                'design' => ['background_color' => '#1F2937', 'foreground_color' => '#FFFFFF', 'label_color' => '#0FA7B4', 'card_title' => 'Café Demo'],
            ]);
            $program->rewards()->create(['business_id' => $business->id, 'name' => 'Café gratis', 'type' => 'free_product', 'stamps_required' => 8, 'consumes_stamps' => true, 'status' => 'active']);
        }
    }
}
