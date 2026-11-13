<?php

namespace App\Support;

use App\Models\Business;

/** Contenedor del negocio activo en la petición actual. */
class Tenant
{
    private ?Business $business = null;

    public function set(?Business $business): void
    {
        $this->business = $business;
    }

    public function get(): ?Business
    {
        return $this->business;
    }

    public function id(): ?int
    {
        return $this->business?->id;
    }
}
