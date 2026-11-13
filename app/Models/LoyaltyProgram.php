<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LoyaltyProgram extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'design' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'amount_per_stamp' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $p) => $p->uuid ??= (string) Str::uuid());
    }

    public function rewards()
    {
        return $this->hasMany(Reward::class, 'program_id');
    }

    public function versions()
    {
        return $this->hasMany(ProgramVersion::class, 'program_id');
    }

    public function stampRules()
    {
        return $this->hasMany(StampRule::class, 'program_id');
    }

    public function cards()
    {
        return $this->hasMany(CustomerCard::class, 'program_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'active'
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->ends_at || $this->ends_at->isFuture());
    }

    /** Campos que, al cambiar, generan nueva versiÃ³n. */
    public const VERSIONED = [
        'name', 'description', 'stamps_goal', 'rule_type', 'amount_per_stamp', 'max_stamps_per_operation',
        'cycle_policy', 'starts_at', 'ends_at', 'terms', 'design',
    ];

    public function snapshot(): array
    {
        return collect(self::VERSIONED)->mapWithKeys(fn ($k) => [$k => $this->{$k}])->all();
    }
}
