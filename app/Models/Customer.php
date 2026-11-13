<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['birthdate' => 'date', 'anonymized_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->uuid ??= (string) Str::uuid());
    }

    public function businesses()
    {
        return $this->belongsToMany(Business::class)->withPivot(['status', 'last_activity_at'])->withTimestamps();
    }

    public function cards()
    {
        return $this->hasMany(CustomerCard::class);
    }
}
