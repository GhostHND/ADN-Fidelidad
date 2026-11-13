<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletPass extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['auth_token'];

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime', 'link_opened_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function card()
    {
        return $this->belongsTo(CustomerCard::class, 'card_id')->withoutGlobalScopes();
    }

    public function registrations()
    {
        return $this->hasMany(AppleWalletRegistration::class, 'pass_id');
    }
}
