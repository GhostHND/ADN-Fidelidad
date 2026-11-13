<?php

namespace App\Services\Wallet;

use App\Models\CustomerCard;
use App\Models\LoyaltyProgram;
use App\Models\Reward;
use App\Models\RewardEntitlement;
use Illuminate\Support\Facades\Storage;

/** Datos neutrales de una tarjeta, comunes a la web, Apple Wallet y Google Wallet. */
class CardView
{
    public function __construct(public readonly CustomerCard $card) {}

    public static function for(CustomerCard $card): self
    {
        $card->loadMissing(['customer', 'program']);

        return new self($card);
    }

    public function program(): LoyaltyProgram
    {
        return $this->card->program;
    }

    public function business()
    {
        return \App\Models\Business::findOrFail($this->card->business_id);
    }

    public function design(): array
    {
        $b = $this->business();
        $d = $this->program()->design ?? [];

        return $d + [
            'background_color' => $b->secondary_color,
            'foreground_color' => '#FFFFFF',
            'label_color' => $b->primary_color,
        ];
    }

    public function goal(): int
    {
        return max(1, $this->program()->stamps_goal);
    }

    /** Sellos visibles en el ciclo actual (limitado a la meta para dibujar). */
    public function shown(): int
    {
        return min($this->card->stamps_balance, $this->goal());
    }

    public function progressText(): string
    {
        return $this->card->stamps_balance.' de '.$this->goal();
    }

    public function nextReward(): ?Reward
    {
        return Reward::withoutGlobalScopes()->where('program_id', $this->card->program_id)->where('status', 'active')
            ->where('stamps_required', '>', $this->card->stamps_balance)->orderBy('stamps_required')->first();
    }

    public function availableRewards()
    {
        return RewardEntitlement::withoutGlobalScopes()->with('reward')->where('card_id', $this->card->id)
            ->where('status', 'available')->get();
    }

    public function rewardSummary(): string
    {
        $n = $this->availableRewards()->count();
        if ($n > 0) {
            return $n === 1 ? '1 recompensa disponible' : "{$n} recompensas disponibles";
        }
        $next = $this->nextReward();

        return $next ? $next->name.' a los '.$next->stamps_required.' sellos' : 'Sigue acumulando sellos';
    }

    public function assetUrl(?int $id): ?string
    {
        if (! $id) {
            return null;
        }
        $asset = \App\Models\MediaAsset::withoutGlobalScopes()->where('business_id', $this->card->business_id)->find($id);

        return $asset ? url(Storage::url($asset->path)) : null;
    }

    public function stripUrl(): string
    {
        return url('/tarjeta/'.$this->card->uuid.'/strip-'.$this->card->revision.'.png');
    }
}
