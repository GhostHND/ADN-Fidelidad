<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleWalletNotification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime', 'next_attempt_at' => 'datetime'];
    }
}
