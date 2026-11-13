<?php

namespace App\Providers;

use App\Support\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Tenant::class);
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Blade::if('perm', function (string $permission) {
            $user = auth()->user();
            $business = app(Tenant::class)->get();
            if (! $user || ! $business) {
                return false;
            }

            return ($user->is_superadmin && session()->has('impersonate_business_id')) || $user->hasPermissionIn($business, $permission);
        });

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('register', fn (Request $r) => Limit::perMinute(6)->by($r->ip()));
        RateLimiter::for('scan', fn (Request $r) => Limit::perMinute(120)->by(($r->user()?->id ?? 'g').'|'.$r->ip()));
    }
}
