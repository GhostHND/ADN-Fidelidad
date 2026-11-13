<?php

use App\Http\Controllers\Api\MasterBusinessController;
use App\Http\Controllers\Api\MasterEntitlementController;
use App\Http\Middleware\VerifyMasterBusinessSignature;
use Illuminate\Support\Facades\Route;

// Contrato versionado v1 con ADN Publicidad Software Master (ver docs/API_SOFTWARE_MASTER.md)
Route::prefix('v1')->group(function () {
    Route::middleware([VerifyMasterBusinessSignature::class, 'throttle:60,1'])->group(function () {
        Route::post('master/businesses/search', [MasterBusinessController::class, 'search']);
        Route::post('master/businesses/link', [MasterBusinessController::class, 'link']);
    });
    Route::post('master/entitlements', MasterEntitlementController::class)
        ->middleware(['master.signed', 'throttle:60,1'])
        ->name('api.master.entitlements');
});
