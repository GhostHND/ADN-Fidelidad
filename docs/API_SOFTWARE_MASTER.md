# Contrato API v1 — Software Master → ADN Fidelidad

> Fidelidad implementa y prueba la recepción de eventos. Master incluye ahora
> el emisor/outbox y la consulta de reconciliación; la integración sigue
> pendiente de credenciales, despliegue y verificación con negocios reales.

`POST /api/v1/master/entitlements`

Cabeceras: `X-ADN-Timestamp` (epoch s), `X-ADN-Idempotency-Key`, `X-ADN-Signature` = `hex(HMAC_SHA256(ts + "\n" + key + "\n" + body, MASTER_WEBHOOK_SECRET))`.

Cuerpo:
```json
{
  "event_id": "evt-1", "event_type": "subscription.updated", "event_version": 5,
  "product_code": "adn-fidelidad",
  "business": {"master_business_id": "M-1"},
  "subscription": {"id": "sub-1", "plan": "pro", "status": "active",
    "starts_at": null, "ends_at": null, "features": ["reports"], "limits": {"max_programs": 3}}
}
```
Respuestas: 200 aplicado / `duplicate` / `ignored` (versión antigua) · 401 firma o hora inválida · 404 negocio desconocido · 409 repetición · 422 payload inválido · 503 sin secreto configurado.

`master:pull` firma un GET contra `MASTER_API_URL` para reconciliar:

`GET /api/v1/integrations/{product_code}/subscriptions/{master_business_id}`

Cabeceras: `Authorization: Bearer MASTER_API_KEY`,
`X-ADN-Timestamp`, `X-ADN-Idempotency-Key` y `X-ADN-Signature` =
`hex(HMAC_SHA256(ts + "\n" + key + "\n" + "", MASTER_API_SECRET))`.

## Vinculación de negocios existentes

`POST /api/v1/master/businesses/search` recibe `{"query":"nombre"}` y devuelve
hasta 20 negocios existentes con `uuid`, `name` y `master_business_id`.
`POST /api/v1/master/businesses/link` recibe `business_uuid` y
`master_business_id` (ambos UUID). El UUID público de `software_instances` es
el `master_business_id`. Repetir el mismo vínculo es idempotente; se rechazan
las reasignaciones y negocios sin vínculo con historial de sincronización.
La operación registra auditoría sin cambiar usuarios ni acceso.

Ambas rutas usan `MASTER_LINK_SECRET`, independiente de los secretos de
eventos y con al menos 32 bytes. Debe coincidir con `FIDELIDAD_LINK_SECRET` en
Master. Las cabeceras son las mismas que para eventos, pero la firma es
`hex(HMAC_SHA256("POST\n" + ruta + "\n" + timestamp + "\n" + uuid + "\n" + cuerpo, MASTER_LINK_SECRET))`.
La caché de Fidelidad debe ser compartida entre procesos y admitir `add`
atómico para la protección antirrepetición. No publiques los secretos.

Antes de vincular, revisa las suscripciones y eventos pendientes en Master:
la sincronización posterior puede modificar el acceso, aunque vincular por sí
solo no lo haga ni confirme pagos. Master reconcilia cambios mediante
`php artisan fidelidad:sync`, guarda primero los eventos en una outbox durable y
los reintenta con backoff; configurar el scheduler/cron es requisito operativo.
