<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;

class OnboardingGuides
{
    public function forRoute(?string $route, User $user, ?Business $business, bool $isAdmin, bool $impersonating): ?array
    {
        foreach (config('onboarding') as $key => $guide) {
            if (in_array($route, $guide['routes'], true) && $this->allowed($key, $user, $business, $isAdmin, $impersonating)) {
                return ['key' => $key] + $guide;
            }
        }

        return null;
    }

    public function allowed(string $key, User $user, ?Business $business, bool $isAdmin, bool $impersonating): bool
    {
        $guide = config('onboarding.'.$key);
        if (! is_array($guide)) {
            return false;
        }
        if ($isAdmin) {
            return $user->is_superadmin && $guide['area'] === 'admin';
        }

        return $business && $guide['area'] === 'business'
            && (($user->is_superadmin && $impersonating) || $user->hasPermissionIn($business, $guide['permission']));
    }
}
