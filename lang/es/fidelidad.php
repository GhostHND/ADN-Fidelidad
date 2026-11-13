<?php

return [
    'tx' => ['earn' => 'Sellos asignados', 'reverse' => 'Reversión', 'adjust' => 'Corrección', 'consume' => 'Consumo (canje/ciclo)'],
    'status' => [
        'active' => 'Activo', 'trial' => 'Prueba', 'past_due' => 'Pago pendiente', 'suspended' => 'Suspendido',
        'expired' => 'Vencido', 'cancelled' => 'Cancelado', 'sin_suscripcion' => 'Sin suscripción registrada',
        'draft' => 'Borrador', 'paused' => 'Pausado', 'archived' => 'Archivado', 'inactive' => 'Inactivo', 'blocked' => 'Bloqueado',
        'available' => 'Disponible', 'redeemed' => 'Canjeada', 'void' => 'Anulada', 'expired_ent' => 'Vencida',
    ],
    'mode' => ['full' => 'Operación completa', 'readonly' => 'Sólo lectura', 'blocked' => 'Bloqueado'],
    'rule_type' => [
        'per_purchase' => 'Un sello por compra', 'per_amount' => 'Sellos por monto de consumo',
        'per_service' => 'Sellos por servicio', 'promotional' => 'Sellos promocionales',
    ],
    'cycle' => [
        'reset' => 'Reiniciar a cero al completar', 'continue' => 'Continuar acumulando',
        'keep_remainder' => 'Reiniciar conservando el excedente',
    ],
    'reward_type' => [
        'free_product' => 'Producto gratis', 'free_service' => 'Servicio gratis', 'percent_discount' => 'Descuento porcentual',
        'fixed_discount' => 'Descuento fijo', 'special' => 'Beneficio especial', 'custom' => 'Personalizada',
    ],
    'role' => ['owner' => 'Propietario', 'manager' => 'Gerente', 'cashier' => 'Cajero'],
    'wallet_status' => ['generated' => 'Generado', 'link_opened' => 'Enlace abierto', 'installed_verified' => 'Instalación verificada', 'failed' => 'Falló'],
    'feature' => [
        'wallet_apple' => 'Apple Wallet', 'wallet_google' => 'Google Wallet', 'reports' => 'Reportes',
        'export' => 'Exportación', 'multiple_programs' => 'Múltiples programas', 'staff' => 'Empleados',
    ],
];
