<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Services\SubscriptionService;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Determina el negocio activo del usuario y activa el aislamiento por negocio. */
class BusinessContext
{
    public function __construct(private Tenant $tenant, private SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403);

        $memberships = $user->businesses()->wherePivot('is_active', true)->get();
        $business = null;
        if ($user->is_superadmin && $request->session()->has('impersonate_business_id')) {
            $business = Business::find($request->session()->get('impersonate_business_id'));
        }
        if (! $business) {
            $wanted = $request->session()->get('business_id');
            $business = $memberships->firstWhere('id', $wanted) ?? $memberships->first();
        }
        abort_unless($business, 403, 'Tu cuenta no está asociada a ningún negocio.');

        $request->session()->put('business_id', $business->id);
        $this->tenant->set($business);
        $request->attributes->set('business', $business);
        $request->attributes->set('memberships', $memberships);
        $request->attributes->set('subscription_mode', $this->subscriptions->mode($business));
        view()->share('currentBusiness', $business);
        view()->share('subscriptionMode', $request->attributes->get('subscription_mode'));
        view()->share('memberships', $memberships);

        return $next($request);
    }
}
