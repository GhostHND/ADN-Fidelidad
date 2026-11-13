<?php

use App\Models\Business;
use App\Models\User;
use App\Services\Master\MasterSyncService;
use App\Services\Wallet\GoogleWalletNotifications;
use App\Services\Wallet\WalletManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('wallet:process {--limit=50} {--business=} {--platform=}', function (WalletManager $wallet, GoogleWalletNotifications $notifications) {
    $business = $this->option('business');
    $platform = $this->option('platform');
    $limit = $this->option('limit');
    if (! ctype_digit((string) $limit) || (int) $limit < 1
        || ($business !== null && (! ctype_digit((string) $business) || (int) $business < 1))
        || ($platform !== null && ! in_array($platform, ['apple', 'google'], true))) {
        $this->error('Indica un límite y negocio numéricos positivos, y una plataforma apple o google.');

        return 1;
    }
    [$done, $failed, $skipped] = $wallet->processDue((int) $limit, $business === null ? null : (int) $business, $platform);
    $this->info("Wallet: {$done} procesados, {$failed} fallidos, {$skipped} omitidos.");
    if ($platform === null || $platform === 'google') {
        [$sent, $notificationFailures, $notificationSkipped] = $notifications->processDue((int) $limit, $business === null ? null : (int) $business);
        $this->info("Avisos Google: {$sent} aceptados, {$notificationFailures} con errores, {$notificationSkipped} omitidos.");
        $failed += $notificationFailures;
    }

    return $failed > 0 ? 1 : 0;
})->purpose('Procesa la cola de sincronización con Apple/Google Wallet');

Artisan::command('master:pull', function (MasterSyncService $master) {
    foreach (Business::whereNotNull('master_business_id')->get() as $business) {
        $r = $master->pull($business);
        $this->line("{$business->name}: {$r['status']} — {$r['message']}");
    }
})->purpose('Consulta a Software Master el estado de suscripción (respaldo del webhook)');

Artisan::command('fidelidad:create-superadmin {email} {name}', function () {
    $password = $this->secret('Contraseña (mínimo 10 caracteres)');
    if (strlen((string) $password) < 10) {
        $this->error('La contraseña debe tener al menos 10 caracteres.');

        return 1;
    }
    $user = User::firstOrNew(['email' => $this->argument('email')]);
    $user->name = $this->argument('name');
    $user->password = $password;
    $user->is_active = true;
    $user->is_superadmin = true;
    $user->save();
    $this->info('Superadministrador listo: '.$user->email);

    return 0;
})->purpose('Crea o actualiza un superadministrador');

Schedule::command('wallet:process')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('master:pull')->hourly()->withoutOverlapping();
