<?php

return [
    'product_code' => 'adn-fidelidad',
    'base_domain' => env('FIDELIDAD_DOMAIN', 'fidelidad.adnpublicidad.site'),

    'brand' => [
        'name' => 'ADN Fidelidad',
        'signature' => 'powered by ADN Publicidad',
        'color' => '#0FA7B4',
        // Recurso oficial: colocar el archivo en public/brand/ (ver docs/DESPLIEGUE.md)
        'logo' => env('BRAND_LOGO_PATH', 'brand/adn-publicidad-logo.png'),
    ],

    'permissions' => [
        'dashboard.view' => 'Ver dashboard y reportes',
        'business.manage' => 'Editar Mi negocio y sucursales',
        'staff.manage' => 'Gestionar empleados',
        'programs.manage' => 'Gestionar tarjetas, programas y recompensas',
        'customers.view' => 'Consultar clientes',
        'customers.export' => 'Exportar clientes',
        'stamps.assign' => 'Escanear y asignar sellos',
        'rewards.redeem' => 'Canjear recompensas',
        'stamps.reverse' => 'Revertir / corregir sellos',
        'history.view' => 'Ver historial completo',
    ],

    'roles' => [
        'owner' => ['*'],
        'manager' => [
            'dashboard.view', 'programs.manage', 'customers.view', 'customers.export',
            'stamps.assign', 'rewards.redeem', 'stamps.reverse', 'history.view',
        ],
        'cashier' => ['stamps.assign', 'rewards.redeem'],
    ],

    'plan_features' => [
        // Funcionalidades que Software Master puede habilitar o no
        'wallet_apple', 'wallet_google', 'reports', 'export', 'multiple_programs', 'staff',
    ],

    'limits_default' => [
        'max_programs' => 1,
        'max_branches' => 1,
        'max_staff' => 3,
        'max_customers' => 500,
    ],

    'master' => [
        'webhook_secret' => env('MASTER_WEBHOOK_SECRET'),
        'link_secret' => env('MASTER_LINK_SECRET'),
        'api_url' => env('MASTER_API_URL'),
        'api_key' => env('MASTER_API_KEY'),
        'api_secret' => env('MASTER_API_SECRET'),
        'max_clock_skew' => 300, // segundos permitidos entre firma y recepción
        'grace_hours' => (int) env('MASTER_GRACE_HOURS', 72), // tolerancia ante fallo de comunicación
    ],

    'wallet' => [
        'google' => [
            'issuer_id' => env('GOOGLE_WALLET_ISSUER_ID'),
            'service_account_json' => env('GOOGLE_WALLET_SERVICE_ACCOUNT_JSON'), // ruta fuera de public/
            'origins' => array_filter(explode(',', (string) env('GOOGLE_WALLET_ORIGINS', ''))),
        ],
        'apple' => [
            'enabled' => env('APPLE_WALLET_ENABLED', false),
            'pass_type_id' => env('APPLE_PASS_TYPE_ID'),
            'team_id' => env('APPLE_TEAM_ID'),
            'certificate_path' => env('APPLE_PASS_CERT_PATH'), // .p12 fuera de public/
            'certificate_password' => env('APPLE_PASS_CERT_PASSWORD'),
            'wwdr_path' => env('APPLE_WWDR_CERT_PATH'), // WWDR en PEM
            'organization' => env('APPLE_ORGANIZATION', 'ADN Publicidad'),
        ],
        'sync_max_attempts' => 6,
    ],

    'uploads' => [
        'max_kb' => 2048,
        'mimes' => ['image/png', 'image/jpeg', 'image/webp'],
    ],
];
