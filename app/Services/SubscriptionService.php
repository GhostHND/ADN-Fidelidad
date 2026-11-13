<?php

namespace App\Services;

use App\Models\Business;
use App\Models\SubscriptionEntitlement;

/**
 * Autorización por suscripción. La fuente de verdad es ADN Publicidad Software Master;
 * aquí sólo se consulta el estado sincronizado y se aplican sus políticas.
 *
 * Modos:
 *  - full:     operación completa.
 *  - readonly: vencida/en gracia agotada. Se conservan datos, saldos y tarjetas visibles; no se otorgan
 *              sellos ni canjes ni se crean programas/clientes nuevos.
 *  - blocked:  suspendida/cancelada/sin suscripción registrada. Sólo consulta administrativa.
 */
class SubscriptionService
{
    public const FULL = 'full';

    public const READONLY = 'readonly';

    public const BLOCKED = 'blocked';

    public function mode(Business $business): string
    {
        if ($business->status !== 'active') {
            return self::BLOCKED;
        }
        $e = $this->entitlement($business);
        if (! $e) {
            return self::BLOCKED;
        }
        if (in_array($e->status, ['suspended', 'cancelled'], true)) {
            return self::BLOCKED;
        }
        if ($e->starts_at && $e->starts_at->isFuture()) {
            return self::BLOCKED;
        }
        if ($e->status === 'expired') {
            return self::READONLY;
        }
        if ($e->ends_at && $e->ends_at->isPast()) {
            // Tolerancia limitada tras el vencimiento para absorber renovaciones en tránsito.
            $grace = now()->subHours((int) config('fidelidad.master.grace_hours'));
            if ($e->ends_at->lt($grace)) {
                return self::READONLY;
            }
        }

        return self::FULL;
    }

    public function canOperate(Business $business): bool
    {
        return $this->mode($business) === self::FULL;
    }

    public function hasFeature(Business $business, string $feature): bool
    {
        if (! $this->canOperate($business)) {
            return false;
        }
        $features = $this->entitlement($business)?->features;

        return is_array($features) && in_array($feature, $features, true);
    }

    public function limit(Business $business, string $key): ?int
    {
        $limits = $this->entitlement($business)?->limits ?? [];

        return array_key_exists($key, $limits)
            ? ($limits[$key] === null ? null : (int) $limits[$key])
            : (config('fidelidad.limits_default.'.$key));
    }

    public function withinLimit(Business $business, string $key, int $current): bool
    {
        $limit = $this->limit($business, $key);

        return $limit === null || $current < $limit;
    }

    public function entitlement(Business $business): ?SubscriptionEntitlement
    {
        return $business->relationLoaded('entitlement') ? $business->entitlement : $business->entitlement()->first();
    }

    public function summary(Business $business): array
    {
        $e = $this->entitlement($business);

        return [
            'mode' => $this->mode($business),
            'plan' => $e?->plan,
            'status' => $e?->status ?? 'sin_suscripcion',
            'ends_at' => $e?->ends_at,
            'last_synced_at' => $e?->last_synced_at,
            'features' => $e?->features ?? [],
        ];
    }

    public static function modeMessage(string $mode): string
    {
        return match ($mode) {
            self::READONLY => 'La suscripción venció. Tus datos se conservan, pero las operaciones están pausadas hasta renovar con ADN Publicidad.',
            self::BLOCKED => 'La suscripción no está activa. Contacta a ADN Publicidad para reactivar el servicio.',
            default => '',
        };
    }
}
