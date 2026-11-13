# ADN Fidelidad

Plataforma SaaS multinegocio de tarjetas de fidelidad con Apple Wallet y Google Wallet (powered by ADN Publicidad).

Stack: Laravel 13 · PHP 8.3 · MySQL (SQLite en pruebas) · Blade + Alpine.js + Tailwind v4 + Vite · cola en base de datos.

## Desarrollo local

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoSeeder   # solo local; usuarios en docs/MANUAL_TECNICO.md
php artisan storage:link
npm run build
php artisan serve
```

## Pruebas

```powershell
php artisan test
```

## Documentación

- [Manual técnico](docs/MANUAL_TECNICO.md)
- [Despliegue en Hostinger](docs/DESPLIEGUE.md)
- [Contrato API con Software Master](docs/API_SOFTWARE_MASTER.md)
- [Configuración de Apple y Google Wallet](docs/WALLETS.md)
- [Manual de administrador y de negocio](docs/MANUAL_USUARIO.md)
- [Estado de verificación (qué se probó y qué no)](docs/ESTADO_VERIFICACION.md)

El logo oficial debe colocarse en `public/brand/adn-publicidad-logo.png` (ruta configurable con `BRAND_LOGO_PATH`).
