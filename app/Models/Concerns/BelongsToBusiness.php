<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;

/** Aislamiento multiempresa: filtra por el negocio activo y lo fija al crear. */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query) {
            $id = app(Tenant::class)->id();
            if ($id !== null) {
                $query->where($query->getModel()->getTable().'.business_id', $id);
            }
        });

        static::creating(function ($model) {
            $id = app(Tenant::class)->id();
            if ($id !== null) {
                if ($model->business_id !== null && (int) $model->business_id !== $id) {
                    throw new \DomainException('Negocio no autorizado.');
                }
                $model->business_id = $id;
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
