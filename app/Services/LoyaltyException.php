<?php

namespace App\Services;

/** Error de regla de negocio con mensaje apto para mostrar al usuario. */
class LoyaltyException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
