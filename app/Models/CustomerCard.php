<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomerCard extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            $c->uuid ??= (string) Str::uuid();
            $c->qr_token ??= Str::random(40);
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function program()
    {
        return $this->belongsTo(LoyaltyProgram::class, 'program_id');
    }

    public function transactions()
    {
        return $this->hasMany(StampTransaction::class, 'card_id');
    }

    public function entitlements()
    {
        return $this->hasMany(RewardEntitlement::class, 'card_id');
    }

    public function cycles()
    {
        return $this->hasMany(LoyaltyCycle::class, 'card_id');
    }

    public function passes()
    {
        return $this->hasMany(WalletPass::class, 'card_id');
    }

    public function qrPayload(): string
    {
        return 'ADNF:'.$this->qr_token;
    }
}
