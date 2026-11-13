<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletSyncJob extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
