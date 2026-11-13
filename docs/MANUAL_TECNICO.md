# Manual técnico

## Arquitectura
- Multi-tenant por `business_id`. Middleware `BusinessContext` fija el negocio activo; el trait `BelongsToBusiness` aplica un global scope. Los accesos a otro negocio devuelven 404 (probado en `TenancyAndPermissionsTest`).
- `Customer`, `User`, `StampRule`, `ProgramVersion`, `WalletPass` no llevan scope global; código público y jobs usan `withoutGlobalScopes()` de forma explícita.
- Roles y permisos definidos en `config/fidelidad.php` (rol en `business_user.role` + permisos JSON). No hay tabla de roles.
- Libro de sellos inmutable (`earn`, `reverse`, `adjust`, `consume`). Idempotencia por clave de operación. Canje y reverso solo una vez.
- Suscripción: modos `full`, `readonly` (vencida o pasada la gracia de 72 h) y `blocked` (inactiva/suspendida/cancelada). Middleware `subscribed` (402 en JSON o mensaje).
- Wallet: `WalletSyncQueue` + comando `wallet:process` (cada minuto). Estados de pase: generated → link_opened → installed_verified (un clic nunca cuenta como instalación).

## Comandos
| Comando | Uso |
|---|---|
| `php artisan wallet:process --limit=50` | Procesa la cola de sincronización de wallets |
| `php artisan master:pull` | Consulta a Software Master (cada hora) |
| `php artisan fidelidad:create-superadmin {email} {name}` | Crea superadministrador (pide contraseña, mín. 10) |
| `php artisan db:seed --class=DemoSeeder` | Datos demo (se niega en producción) |

Demo (solo local): `admin@demo.test`, `dueno@demo.test`, `gerente@demo.test`, `cajero@demo.test`, contraseña `Demo-Password-2026`.

## Seguridad
- Registro público no devuelve el enlace de una tarjeta existente (evita toma de cuenta por correo).
- Servicio web de Apple exige `Authorization: ApplePass {token}` y `passType` coincidente.
- Webhook de Master: HMAC-SHA256 sobre `ts\nkey\nbody`, tolerancia 300 s, anti-replay, rate limit.
- Exportación CSV con protección contra inyección de fórmulas. Credenciales de wallets fuera de `public/`.

## Desviaciones respecto al prompt
- Se usó Blade + Alpine.js en lugar de Livewire.
- Roles/permisos por configuración, no por tabla.
