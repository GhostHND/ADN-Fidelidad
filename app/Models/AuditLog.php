<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('La auditoría es inmutable.'));
        static::deleting(fn () => throw new \LogicException('La auditoría es inmutable.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Business $business = null, ?Model $subject = null, array $meta = [], ?User $user = null): self
    {
        return static::create([
            'business_id' => $business?->id,
            'user_id' => ($user ?? auth()->user())?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
