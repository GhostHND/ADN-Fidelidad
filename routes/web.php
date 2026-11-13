<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Business;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Public\AppleWebServiceController;
use App\Http\Controllers\Public\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect('/app') : redirect('/login'));

// Autenticación
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Público: landing de negocio, registro y tarjeta del cliente
Route::get('/negocio/{slug}', [PublicController::class, 'landing'])->name('public.landing');
Route::post('/negocio/{slug}/registro', [PublicController::class, 'register'])->middleware('throttle:register')->name('public.register');
Route::get('/tarjeta/{uuid}', [PublicController::class, 'card'])->name('public.card');
Route::get('/tarjeta/{uuid}/strip-{revision}.png', [PublicController::class, 'strip'])->where('revision', '[0-9]+');
Route::get('/tarjeta/{uuid}/qr.svg', [PublicController::class, 'qr']);
Route::get('/tarjeta/{uuid}/apple.pkpass', [PublicController::class, 'applePass'])->middleware('throttle:30,1');
Route::get('/tarjeta/{uuid}/google', [PublicController::class, 'google'])->middleware('throttle:30,1');
Route::post('/tarjeta/{uuid}/privacidad', [PublicController::class, 'anonymize'])->middleware('throttle:5,1')->name('public.card.privacy');
Route::get('/manifest.webmanifest', [PublicController::class, 'manifest']);

// Servicio web de Apple Wallet (PassKit)
Route::prefix('wallet/apple/v1')->group(function () {
    Route::post('devices/{device}/registrations/{passType}/{serial}', [AppleWebServiceController::class, 'register']);
    Route::delete('devices/{device}/registrations/{passType}/{serial}', [AppleWebServiceController::class, 'unregister']);
    Route::get('devices/{device}/registrations/{passType}', [AppleWebServiceController::class, 'updated']);
    Route::get('passes/{passType}/{serial}', [AppleWebServiceController::class, 'pass']);
    Route::post('log', [AppleWebServiceController::class, 'log']);
});

// Superadministrador ADN
Route::prefix('admin')->middleware(['auth', 'superadmin'])->name('admin.')->group(function () {
    Route::post('ayuda/{guide}', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::get('/', [Admin\AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('negocios', [Admin\AdminController::class, 'businesses'])->name('businesses');
    Route::get('negocios/nuevo', [Admin\AdminController::class, 'createBusiness'])->name('businesses.create');
    Route::post('negocios', [Admin\AdminController::class, 'storeBusiness'])->name('businesses.store');
    Route::get('negocios/{business}', [Admin\AdminController::class, 'showBusiness'])->name('businesses.show');
    Route::put('negocios/{business}', [Admin\AdminController::class, 'updateBusiness'])->name('businesses.update');
    Route::post('negocios/{business}/sincronizar', [Admin\AdminController::class, 'pullBusiness'])->name('businesses.pull');
    Route::post('negocios/{business}/entrar', [Admin\AdminController::class, 'impersonate'])->name('businesses.impersonate');
    Route::post('salir-negocio', [Admin\AdminController::class, 'leave'])->name('leave');
    Route::get('usuarios', [Admin\AdminController::class, 'users'])->name('users');
    Route::post('usuarios', [Admin\AdminController::class, 'storeUser'])->name('users.store');
    Route::put('usuarios/{user}', [Admin\AdminController::class, 'updateUser'])->name('users.update');
    Route::get('wallet', [Admin\AdminController::class, 'wallet'])->name('wallet');
    Route::post('wallet/procesar', [Admin\AdminController::class, 'processWallet'])->name('wallet.process');
    Route::post('wallet/{job}/reintentar', [Admin\AdminController::class, 'retryJob'])->name('wallet.retry');
    Route::get('integracion', [Admin\AdminController::class, 'events'])->name('events');
    Route::get('auditoria', [Admin\AdminController::class, 'audit'])->name('audit');
    Route::get('sistema', [Admin\AdminController::class, 'system'])->name('system');
});

// Panel del negocio
Route::prefix('app')->middleware(['auth', 'business'])->name('app.')->group(function () {
    Route::post('ayuda/{guide}', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::get('/', [Business\DashboardController::class, 'index'])->name('dashboard')->middleware('can.do:dashboard.view');
    Route::post('cambiar-negocio', [Business\DashboardController::class, 'switch'])->name('switch');

    Route::get('mi-negocio', [Business\BusinessController::class, 'edit'])->name('business')->middleware('can.do:business.manage');
    Route::put('mi-negocio', [Business\BusinessController::class, 'update'])->middleware(['can.do:business.manage', 'subscribed'])->name('business.update');
    Route::post('sucursales', [Business\BusinessController::class, 'storeBranch'])->middleware(['can.do:business.manage', 'subscribed'])->name('branches.store');
    Route::put('sucursales/{branch}', [Business\BusinessController::class, 'updateBranch'])->middleware(['can.do:business.manage', 'subscribed'])->name('branches.update');

    Route::get('empleados', [Business\StaffController::class, 'index'])->name('staff')->middleware('can.do:staff.manage');
    Route::post('empleados', [Business\StaffController::class, 'store'])->middleware(['can.do:staff.manage', 'subscribed'])->name('staff.store');
    Route::put('empleados/{user}', [Business\StaffController::class, 'update'])->middleware(['can.do:staff.manage', 'subscribed'])->name('staff.update');

    Route::middleware('can.do:programs.manage')->group(function () {
        Route::get('tarjetas', [Business\ProgramController::class, 'index'])->name('programs');
        Route::get('tarjetas/nueva', [Business\ProgramController::class, 'create'])->name('programs.create');
        Route::get('tarjetas/{program}', [Business\ProgramController::class, 'edit'])->name('programs.edit');
        Route::middleware('subscribed')->group(function () {
            Route::post('tarjetas', [Business\ProgramController::class, 'store'])->name('programs.store');
            Route::put('tarjetas/{program}', [Business\ProgramController::class, 'update'])->name('programs.update');
            Route::post('tarjetas/{program}/estado', [Business\ProgramController::class, 'status'])->name('programs.status');
            Route::post('tarjetas/{program}/duplicar', [Business\ProgramController::class, 'duplicate'])->name('programs.duplicate');
            Route::post('tarjetas/{program}/recompensas', [Business\ProgramController::class, 'storeReward'])->name('rewards.store');
            Route::put('recompensas/{reward}', [Business\ProgramController::class, 'updateReward'])->name('rewards.update');
            Route::post('tarjetas/{program}/reglas', [Business\ProgramController::class, 'storeRule'])->name('rules.store');
            Route::put('reglas/{rule}', [Business\ProgramController::class, 'updateRule'])->name('rules.update');
            Route::post('medios', [Business\MediaController::class, 'store'])->name('media.store');
        });
    });

    Route::middleware('can.do:customers.view')->group(function () {
        Route::get('clientes', [Business\CustomerController::class, 'index'])->name('customers');
        Route::get('clientes/{card}', [Business\CustomerController::class, 'show'])->name('customers.show');
    });
    Route::get('clientes-exportar', [Business\CustomerController::class, 'export'])->middleware('can.do:customers.export')->name('customers.export');
    Route::post('clientes/{card}/ajuste', [Business\CustomerController::class, 'adjust'])->middleware(['can.do:stamps.reverse', 'subscribed'])->name('customers.adjust');
    Route::post('movimientos/{transaction}/revertir', [Business\CustomerController::class, 'reverse'])->middleware(['can.do:stamps.reverse', 'subscribed'])->name('transactions.reverse');

    Route::get('escanear', [Business\ScanController::class, 'index'])->name('scan')->middleware('can.do:stamps.assign');
    Route::middleware(['throttle:scan', 'subscribed'])->group(function () {
        Route::post('escanear/buscar', [Business\ScanController::class, 'lookup'])->middleware('can.do:stamps.assign')->name('scan.lookup');
        Route::post('escanear/sellos', [Business\ScanController::class, 'award'])->middleware('can.do:stamps.assign')->name('scan.award');
        Route::post('escanear/canje', [Business\ScanController::class, 'redeem'])->middleware('can.do:rewards.redeem')->name('scan.redeem');
    });

    Route::get('historial', [Business\HistoryController::class, 'index'])->name('history')->middleware('can.do:history.view');
    Route::get('reportes', [Business\HistoryController::class, 'reports'])->name('reports')->middleware('can.do:dashboard.view');
    Route::get('suscripcion', [Business\DashboardController::class, 'subscription'])->name('subscription');
});
