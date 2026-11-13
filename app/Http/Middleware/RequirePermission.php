<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $business = $request->attributes->get('business');
        $allowed = $user && $business && (
            ($user->is_superadmin && $request->session()->has('impersonate_business_id'))
            || $user->hasPermissionIn($business, $permission)
        );
        if (! $allowed) {
            return $this->deny($request, 'No tienes permisos para realizar esta acción.');
        }

        return $next($request);
    }

    private function deny(Request $request, string $msg): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $msg], 403);
        }
        abort(403, $msg);
    }
}
