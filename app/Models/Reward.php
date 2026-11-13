<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'consumes_stamps' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'value' => 'decimal:2',
        ];
    }

    public function program()
    {
        return $this->belongsTo(LoyaltyProgram::class, 'program_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'active'
            && (! $this->valid_from || $this->valid_from->isPast())
            && (! $this->valid_until || $this->valid_until->isFuture());
    }
}
