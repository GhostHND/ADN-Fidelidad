<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyCycle extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
