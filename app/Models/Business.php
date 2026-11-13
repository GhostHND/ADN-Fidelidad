<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Business extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['social_links' => 'array', 'opening_hours' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $b) {
            $b->uuid ??= (string) Str::uuid();
            $b->slug = $b->slug ?: static::uniqueSlug($b->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'negocio';
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function branches()
    {
        return $this->hasMany(BusinessBranch::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot(['role', 'permissions', 'branch_id', 'is_active'])->withTimestamps();
    }

    public function customers()
    {
        return $this->belongsToMany(Customer::class)->withPivot(['status', 'consent_privacy', 'consent_marketing', 'last_activity_at'])->withTimestamps();
    }

    public function programs()
    {
        return $this->hasMany(LoyaltyProgram::class);
    }

    public function entitlement()
    {
        return $this->hasOne(SubscriptionEntitlement::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function publicUrl(): string
    {
        return url('/negocio/'.$this->slug);
    }
}
