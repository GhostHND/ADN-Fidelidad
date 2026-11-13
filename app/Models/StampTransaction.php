<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StampTransaction extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->uuid ??= (string) Str::uuid());
        // Los movimientos son inmutables: las correcciones son transacciones nuevas.
        static::updating(fn () => throw new \LogicException('Los movimientos de sellos no son modificables.'));
        static::deleting(fn () => throw new \LogicException('Los movimientos de sellos no se eliminan.'));
    }

    public function card()
    {
        return $this->belongsTo(CustomerCard::class, 'card_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch()
    {
        return $this->belongsTo(BusinessBranch::class, 'branch_id');
    }

    public function program()
    {
        return $this->belongsTo(LoyaltyProgram::class, 'program_id');
    }
}
