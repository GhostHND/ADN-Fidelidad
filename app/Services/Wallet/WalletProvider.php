<?php

namespace App\Services\Wallet;

use App\Models\CustomerCard;
use App\Models\LoyaltyProgram;
use App\Models\WalletPass;

/** Contrato que debe cumplir cada proveedor de Wallet. */
interface WalletProvider
{
    public function platform(): string;

    /** ¿Están presentes las credenciales/certificados necesarios? */
    public function isConfigured(): bool;

    /** Qué falta para operar, en lenguaje claro. */
    public function missingConfig(): array;

    /** Garantiza que exista el pase del cliente (identidad estable) y devuelve el registro. */
    public function ensurePass(CustomerCard $card): WalletPass;

    /** URL de instalación (Google) o de descarga del .pkpass (Apple). */
    public function installUrl(CustomerCard $card): string;

    /** Envía al proveedor el estado actual de una tarjeta. */
    public function syncCard(WalletPass $pass): void;

    /** Actualiza los recursos compartidos del programa (clase en Google). */
    public function syncProgram(LoyaltyProgram $program): void;
}
