<?php

namespace App\Services\Wallet;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\CustomerCard;
use App\Models\LoyaltyProgram;
use App\Models\WalletSyncJob;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Cache;

class WalletManager
{
    public function __construct(
        private GoogleWalletProvider $google,
        private AppleWalletProvider $apple,
        private SubscriptionService $subscriptions,
    ) {}

    /** @return array<string, WalletProvider> */
    public function providers(): array
    {
        return ['apple' => $this->apple, 'google' => $this->google];
    }

    public function provider(string $platform): WalletProvider
    {
        return $this->providers()[$platform] ?? throw new \InvalidArgumentException('Plataforma de Wallet desconocida.');
    }

    public function available(Business $business, string $platform): bool
    {
        $feature = $platform === 'apple' ? 'wallet_apple' : 'wallet_google';

        return ($platform !== 'apple' || config('fidelidad.wallet.apple.enabled'))
            && $this->provider($platform)->isConfigured()
            && $this->subscriptions->hasFeature($business, $feature);
    }

    /** Procesa trabajos vencidos con retroceso exponencial. Devuelve [hechos, fallidos, omitidos]. */
    public function processDue(int $limit = 50, ?int $businessId = null, ?string $platform = null): array
    {
        $done = $failed = $skipped = 0;
        $jobs = WalletSyncJob::where('status', 'pending')
            ->where('next_attempt_at', '<=', now())
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($platform !== null, fn ($query) => $query->where('platform', $platform))
            ->orderBy('id')->limit($limit)->get();

        foreach ($jobs as $job) {
            $resource = $job->scope === 'class' ? 'program-'.$job->program_id : 'card-'.$job->card_id;
            $lock = Cache::lock('wallet-sync-'.$job->platform.'-'.$resource, 180);
            if (! $lock->get()) {
                continue;
            }
            try {
                $job->refresh();
                if ($job->status !== 'pending' || $job->next_attempt_at->isFuture()) {
                    continue;
                }
                $provider = $this->provider($job->platform);
                if (! $provider->isConfigured()) {
                    $job->update(['status' => 'skipped', 'last_error' => 'Proveedor sin configurar: '.implode(', ', $provider->missingConfig()), 'completed_at' => now()]);
                    $skipped++;

                    continue;
                }
                try {
                    if ($job->scope === 'class') {
                        $provider->syncProgram(LoyaltyProgram::withoutGlobalScopes()->findOrFail($job->program_id));
                    } else {
                        $card = CustomerCard::withoutGlobalScopes()->findOrFail($job->card_id);
                        $pass = $card->passes()->where('platform', $job->platform)->first();
                        if ($pass) {
                            $provider->syncCard($pass);
                        }
                    }
                    $job->update(['status' => 'done', 'completed_at' => now(), 'attempts' => $job->attempts + 1, 'last_error' => null]);
                    $done++;
                } catch (\Throwable $e) {
                    $attempts = $job->attempts + 1;
                    $max = (int) config('fidelidad.wallet.sync_max_attempts');
                    $job->update([
                        'attempts' => $attempts,
                        'last_error' => mb_substr($e->getMessage(), 0, 1000),
                        'status' => $attempts >= $max ? 'failed' : 'pending',
                        'next_attempt_at' => now()->addMinutes(min(240, 2 ** $attempts)),
                    ]);
                    if ($attempts >= $max) {
                        AuditLog::create(['business_id' => $job->business_id, 'action' => 'wallet.sync_failed', 'subject_type' => 'WalletSyncJob', 'subject_id' => $job->id, 'meta' => ['error' => $job->last_error], 'created_at' => now()]);
                    }
                    $failed++;
                }
            } finally {
                $lock->release();
            }
        }

        return [$done, $failed, $skipped];
    }
}
