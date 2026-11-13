<?php

namespace App\Services\Wallet;

use App\Models\CustomerCard;
use App\Models\LoyaltyProgram;
use App\Models\WalletSyncJob;

class WalletSyncQueue
{
    /** Programa la actualización de los pases existentes de una tarjeta. */
    public function queueCard(CustomerCard $card, string $reason): void
    {
        foreach ($card->passes()->pluck('platform') as $platform) {
            WalletSyncJob::create([
                'business_id' => $card->business_id, 'card_id' => $card->id, 'platform' => $platform,
                'scope' => 'object', 'reason' => $reason, 'status' => 'pending', 'next_attempt_at' => now(),
            ]);
        }
    }

    /** Cambio de diseño del programa: actualiza la clase compartida y todos los pases emitidos. */
    public function queueProgram(LoyaltyProgram $program, string $reason): int
    {
        WalletSyncJob::create([
            'business_id' => $program->business_id, 'program_id' => $program->id, 'platform' => 'google',
            'scope' => 'class', 'reason' => $reason, 'status' => 'pending', 'next_attempt_at' => now(),
        ]);
        $n = 0;
        foreach (CustomerCard::withoutGlobalScopes()->where('program_id', $program->id)->has('passes')->cursor() as $card) {
            $card->increment('revision');
            $this->queueCard($card->refresh(), $reason);
            $n++;
        }

        return $n;
    }
}
