<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Misma convención de firma HMAC que el resto de integraciones de ADN Publicidad. */
class VerifyMasterSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('fidelidad.master.webhook_secret');
        if ($secret === '') {
            return response()->json(['ok' => false, 'message' => 'Integración no configurada.'], 503);
        }

        $ts = trim((string) $request->header('X-ADN-Timestamp', ''));
        $key = trim((string) $request->header('X-ADN-Idempotency-Key', ''));
        $signature = strtolower(trim((string) $request->header('X-ADN-Signature', '')));

        if (! preg_match('/^\d{10}$/', $ts) || $key === '' || mb_strlen($key) > 190 || ! preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return response()->json(['ok' => false, 'message' => 'Firma de integración inválida.'], 401);
        }
        if (abs(now()->timestamp - (int) $ts) > max(30, (int) config('fidelidad.master.max_clock_skew'))) {
            return response()->json(['ok' => false, 'message' => 'La solicitud de integración expiró.'], 401);
        }
        $expected = hash_hmac('sha256', $ts."\n".$key."\n".$request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['ok' => false, 'message' => 'Firma de integración inválida.'], 401);
        }

        // Protección contra reproducción: cada clave de idempotencia firmada sólo se acepta una vez.
        $replayKey = 'master-replay:'.hash('sha256', $key);
        if (! \Illuminate\Support\Facades\Cache::add($replayKey, 1, 2 * max(30, (int) config('fidelidad.master.max_clock_skew')))) {
            return response()->json(['ok' => false, 'message' => 'Solicitud repetida.'], 409);
        }

        return $next($request);
    }
}
