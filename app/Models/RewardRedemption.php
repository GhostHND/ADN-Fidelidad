<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class RewardRedemption extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['redeemed_at' => 'datetime'];
    }

    public function entitlement()
    {
        return $this->belongsTo(RewardEntitlement::class, 'entitlement_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch()
    {
        return $this->belongsTo(BusinessBranch::class, 'branch_id');
    }
}
