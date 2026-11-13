<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppleWalletRegistration extends Model
{
    protected $guarded = ['id'];

    public function device()
    {
        return $this->belongsTo(AppleWalletDevice::class, 'device_id');
    }

    public function pass()
    {
        return $this->belongsTo(WalletPass::class, 'pass_id');
    }
}
