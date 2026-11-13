<?php

use App\Http\Middleware\BusinessContext;
use App\Http\Middleware\RequireActiveSubscription;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireSuperadmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyMasterSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        $middleware->redirectGuestsTo('/login');
        // El contexto de negocio debe existir antes de resolver modelos por ruta (aislamiento multiempresa).
        $middleware->prependToPriorityList(before: \Illuminate\Routing\Middleware\SubstituteBindings::class, prepend: BusinessContext::class);
        $middleware->alias([
            'business' => BusinessContext::class,
            'can.do' => RequirePermission::class,
            'superadmin' => RequireSuperadmin::class,
            'subscribed' => RequireActiveSubscription::class,
            'master.signed' => VerifyMasterSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
