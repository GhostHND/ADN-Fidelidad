# Estado de verificación

## Verificado (38 pruebas automáticas, `php artisan test`, todas pasan)
Páginas de negocio/admin/públicas (200), libro de sellos (idempotencia, límites, canje/reverso una vez, inmutabilidad, QR de otro negocio), aislamiento entre negocios, permisos por rol, modos de suscripción, contrato Master (firma, hora, replay, duplicados, versión antigua, negocio desconocido, payload inválido, suspensión), registro público (consentimiento, sin filtración de tarjetas existentes, anonimización), empaquetado y firma de `.pkpass` con certificados autofirmados de prueba, servicio web Apple (token, registro, actualizaciones), payloads de Google Wallet. `npm run build` correcto.

## NO verificado (requiere credenciales o dispositivos)
- Apple Wallet real: instalación en iPhone, firma con certificado Apple real y notificaciones APNs.
- Google Wallet real: llamadas a la API con cuenta de emisor real.
- Software Master real: no existe aún módulo de suscripciones en su repositorio; solo se probó el contrato localmente.
- Escáner con cámara (`html5-qrcode`) en un dispositivo real; despliegue real en Hostinger; carga/rendimiento.
- Flujos sin prueba automática: subida de medios, exportación CSV, alta de personal/programas desde formularios, alta de negocio en admin.
