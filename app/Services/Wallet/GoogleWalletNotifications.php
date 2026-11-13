<?php

namespace App\Services\Wallet;

use App\Models\CustomerCard;
use App\Models\GoogleWalletNotification;
use App\Models\RewardEntitlement;
use App\Models\StampTransaction;
use App\Models\WalletPass;
use App\Models\WalletSyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleWalletNotifications
{
    public function __construct(private GoogleWalletProvider $google) {}

    public function queue(CustomerCard $card, StampTransaction $transaction, bool $reward): void
    {
        $pass = $card->passes()->where('platform', 'google')->first();
        if ($pass) {
            GoogleWalletNotification::firstOrCreate(
                ['wallet_pass_id' => $pass->id, 'stamp_transaction_id' => $transaction->id],
                ['business_id' => $card->business_id, 'kind' => $reward ? 'reward' : 'stamp'],
            );
        }
    }

    /** @return array{int, int, int} */
    public function processDue(int $limit = 50, ?int $businessId = null): array
    {
        $sent = $failed = $skipped = 0;
        $ids = GoogleWalletNotification::where('status', 'pending')
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('wallet_passes as wp')
                    ->join('customer_cards as cc', 'cc.id', '=', 'wp.card_id')
                    ->whereColumn('wp.id', 'google_wallet_notifications.wallet_pass_id')
                    ->where(function ($query) {
                        $query->where(function ($query) {
                            $query->where('wp.status', 'installed_verified')
                                ->whereColumn('wp.synced_revision', '>=', 'cc.revision');
                        })->orWhereNotExists(function ($query) {
                            $query->selectRaw('1')->from('wallet_sync_jobs as sync')
                                ->whereColumn('sync.card_id', 'wp.card_id')
                                ->where('sync.platform', 'google')->where('sync.status', 'pending');
                        });
                    });
            })
            ->orderByRaw("case when kind = 'reward' then 0 else 1 end")
            ->orderBy('id')->limit($limit)->pluck('id');

        foreach ($ids as $id) {
            $notification = DB::transaction(function () use ($id) {
                $candidate = GoogleWalletNotification::find($id);
                if (! $candidate) {
                    return null;
                }
                $pass = WalletPass::whereKey($candidate->wallet_pass_id)->lockForUpdate()->first();
                $notification = GoogleWalletNotification::whereKey($id)->lockForUpdate()->first();
                if (! $pass || ! $notification || $notification->status !== 'pending'
                    || ($notification->next_attempt_at && $notification->next_attempt_at->isFuture())) {
                    return null;
                }

                GoogleWalletNotification::where('wallet_pass_id', $pass->id)->where('status', 'sending')
                    ->where('attempted_at', '<', now()->subMinutes(5))
                    ->update(['status' => 'uncertain', 'last_error' => 'El proceso terminó sin confirmar la respuesta de Google.']);
                if (GoogleWalletNotification::where('wallet_pass_id', $pass->id)->where('status', 'sending')->exists()) {
                    return null;
                }

                $card = CustomerCard::withoutGlobalScopes()->findOrFail($pass->card_id);
                if ($pass->status !== 'installed_verified' || $pass->synced_revision < $card->revision) {
                    $syncPending = WalletSyncJob::where('card_id', $card->id)->where('platform', 'google')
                        ->where('status', 'pending')->exists();
                    if ($syncPending) {
                        return null;
                    }
                    $notification->update(['status' => 'skipped', 'last_error' => 'La tarjeta no está sincronizada o no se ha verificado su instalación.']);

                    return $notification;
                }
                if ($notification->kind === 'reward' && ! RewardEntitlement::withoutGlobalScopes()
                    ->where('card_id', $card->id)->where('status', 'available')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists()) {
                    $notification->update(['status' => 'skipped', 'last_error' => 'La recompensa ya no está disponible.']);

                    return $notification;
                }
                $used = GoogleWalletNotification::where('wallet_pass_id', $pass->id)
                    ->whereIn('status', ['sending', 'sent', 'uncertain'])
                    ->where('attempted_at', '>', now()->subHours(24))->count();
                $maximum = $notification->kind === 'reward' ? 3 : 2;
                if ($used >= $maximum) {
                    $notification->update(['status' => 'skipped', 'last_error' => 'Límite de notificaciones reservado para recompensas.']);

                    return $notification;
                }
                $notification->update(['status' => 'sending', 'attempted_at' => now()]);

                return $notification;
            });

            if (! $notification) {
                continue;
            }
            if ($notification->status === 'skipped') {
                $skipped++;

                continue;
            }
            try {
                $this->google->sendNotification(WalletPass::findOrFail($notification->wallet_pass_id), $notification);
                $notification->update(['status' => 'sent', 'last_error' => null]);
                $sent++;
            } catch (GoogleWalletNotificationPreflightException $e) {
                $attempts = $notification->attempts + 1;
                $notification->update([
                    'status' => $attempts >= (int) config('fidelidad.wallet.sync_max_attempts') ? 'failed' : 'pending',
                    'attempts' => $attempts,
                    'attempted_at' => null,
                    'next_attempt_at' => now()->addMinutes(min(240, 2 ** $attempts)),
                    'last_error' => mb_substr($e->getMessage(), 0, 1000),
                ]);
                Log::error('No se pudo preparar la notificación de Google Wallet.', [
                    'notification_id' => $notification->id, 'exception' => $e,
                ]);
                $failed++;
            } catch (\Throwable $e) {
                $notification->update(['status' => 'uncertain', 'last_error' => mb_substr($e->getMessage(), 0, 1000)]);
                Log::error('No se pudo confirmar la notificación de Google Wallet.', [
                    'notification_id' => $notification->id, 'exception' => $e,
                ]);
                $failed++;
            }
        }

        return [$sent, $failed, $skipped];
    }
}
