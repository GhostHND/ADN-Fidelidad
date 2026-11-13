<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bloquea acciones que modifican datos operativos cuando la suscripción no está en modo completo. */
class RequireActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = $request->attributes->get('subscription_mode');
        if ($mode !== 'full') {
            $msg = \App\Services\SubscriptionService::modeMessage($mode);
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'La suscripción no permite esta funcionalidad. '.$msg], 402);
            }

            return back()->with('error', 'La suscripción no permite esta funcionalidad. '.$msg);
        }

        return $next($request);
    }
}
