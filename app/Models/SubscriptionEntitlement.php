<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionEntitlement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'features' => 'array',
            'limits' => 'array',
        ];
    }
}
