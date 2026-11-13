<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RewardEntitlement extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['earned_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $e) => $e->uuid ??= (string) Str::uuid());
    }

    public function reward()
    {
        return $this->belongsTo(Reward::class);
    }

    public function card()
    {
        return $this->belongsTo(CustomerCard::class, 'card_id');
    }

    public function redemption()
    {
        return $this->hasOne(RewardRedemption::class, 'entitlement_id');
    }
}
