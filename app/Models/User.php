<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_superadmin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function businesses()
    {
        return $this->belongsToMany(Business::class)->withPivot(['role', 'permissions', 'branch_id', 'is_active'])->withTimestamps();
    }

    public function membership(Business $business): ?object
    {
        $row = $this->businesses()->where('businesses.id', $business->id)->first();

        return $row && $row->pivot->is_active ? $row->pivot : null;
    }

    public function hasPermissionIn(Business $business, string $permission): bool
    {
        $m = $this->membership($business);
        if (! $m) {
            return false;
        }
        $perms = $m->permissions;
        if (is_string($perms)) {
            $perms = json_decode($perms, true);
        }
        $perms = $perms ?: config('fidelidad.roles.'.$m->role, []);

        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }
}
