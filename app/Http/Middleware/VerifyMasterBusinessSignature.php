<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifyMasterBusinessSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('fidelidad.master.link_secret');
        if (strlen($secret) < 32) {
            return response()->json(['message' => 'Vinculación con Master no configurada.'], 503);
        }

        $timestamp = (string) $request->header('X-ADN-Timestamp', '');
        $key = (string) $request->header('X-ADN-Idempotency-Key', '');
        $signature = (string) $request->header('X-ADN-Signature', '');
        if (! preg_match('/\A\d{10}\z/', $timestamp)
            || ! Str::isUuid($key)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $signature)
            || abs(now()->timestamp - (int) $timestamp) > 300) {
            return response()->json(['message' => 'Firma de vinculación inválida.'], 401);
        }

        $signed = $request->method()."\n".$request->getPathInfo()."\n".$timestamp."\n".$key."\n".$request->getContent();
        if (! hash_equals(hash_hmac('sha256', $signed, $secret), $signature)) {
            return response()->json(['message' => 'Firma de vinculación inválida.'], 401);
        }
        if (! Cache::add('master-business-replay:'.hash('sha256', $key), 1, 600)) {
            return response()->json(['message' => 'Solicitud repetida.'], 409);
        }

        return $next($request);
    }
}
