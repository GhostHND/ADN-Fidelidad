# Manual de usuario

## Superadministrador (`/admin`)
Panel con negocios (crear, ver, sincronizar con Master, impersonar), usuarios, cola de wallets (procesar/reintentar), eventos de Master, auditoría y estado del sistema. Los derechos manuales se registran como entitlement `manual`.

## Negocio (`/app`)
- **Dueño/Gerente:** panel, suscripción, perfil, personal (con límites del plan), programas (con versiones, reglas y recompensas), clientes, historial y reportes (según plan).
- **Cajero:** escáner (cámara QR o código), registrar sellos, canjear recompensas. Si tiene sucursal asignada, sus operaciones se fuerzan a ella.
- Con suscripción vencida el panel es de solo lectura; suspendida/cancelada bloquea.

## Cliente (público)
Escanea el QR del negocio (`/negocio/{slug}`), se registra aceptando la privacidad, y obtiene su tarjeta con QR; puede agregarla a Apple/Google Wallet (si están configurados) y anonimizar sus datos.
