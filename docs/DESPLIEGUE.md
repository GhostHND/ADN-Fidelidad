# Despliegue en Hostinger

1. PHP 8.3 con extensiones: `openssl, curl, mbstring, pdo_mysql, zip, gd, intl, fileinfo, xml, bcmath`. HTTP/2 en curl es necesario para notificaciones APNs.
2. Subir el proyecto fuera de `public_html`; apuntar el dominio/subdominio a `public/`. Si no es posible, usar un enlace simbólico o `.htaccess` que dirija a `public/`.
3. `composer install --no-dev --optimize-autoloader` (local o SSH) y `npm run build` (subir `public/build`).
4. Copiar `.env.production.example` a `.env`, completar BD MySQL, `APP_KEY` (`php artisan key:generate`), SMTP y claves.
5. `php artisan migrate --force`, `php artisan storage:link`, `php artisan config:cache route:cache view:cache`.
6. Cron (cada minuto): `* * * * * php /home/USUARIO/proyecto/artisan schedule:run >> /dev/null 2>&1`. Ejecuta `wallet:process` cada minuto y `master:pull` cada hora.
7. Crear superadmin: `php artisan fidelidad:create-superadmin correo nombre`.
8. Certificados (.p12, WWDR, JSON de Google) en una carpeta fuera de `public/` con permisos 600.
9. Logo: `public/brand/adn-publicidad-logo.png`.
10. Respaldos: exportación diaria de MySQL (hPanel o `mysqldump`) y copia de `storage/app` y `.env`. Recuperación: restaurar BD, `.env` y `storage`, luego `php artisan migrate --force` y `wallet:process`.
11. Nunca ejecutar `DemoSeeder` en producción (se bloquea solo).

La conexión MySQL configura las tablas nuevas con InnoDB, necesario para transacciones y claves foráneas. Asegúrate de que InnoDB esté disponible en el servidor.

## Guías de primer uso

Ejecute las migraciones al desplegar para crear la tabla `onboarding_progress`. Las guías aparecen al entrar por primera vez a cada sección compatible, según los permisos; cerrarlas o finalizarlas guarda el progreso por usuario y negocio. El botón Ayuda permite reabrirlas. Si no se puede guardar, se muestra un error y se puede reintentar o cerrar sin guardar; en este último caso la guía volverá a aparecer.

Para procesar solo un negocio durante una prueba, se puede usar `php artisan wallet:process --business=ID --platform=google`. Sin estos filtros el programador procesa todos los negocios. La URL pública HTTPS configurada debe servir las imágenes de las tarjetas procesadas; un túnel temporal no sustituye un dominio estable de producción.

## Tarjetas en iPhone durante el lanzamiento

Apple Wallet está desactivado por defecto mediante `APPLE_WALLET_ENABLED=false`. La tarjeta pública ofrece instrucciones para guardar un acceso directo en la pantalla de inicio de Safari. Es una página web, no un pase Apple Wallet: necesita conexión, puede requerir recargar para ver cambios y no envía notificaciones automáticas. Su enlace permite acceder a la tarjeta y su QR; debe tratarse como privado.

El acceso guardado depende del dominio donde se abrió la tarjeta. Los enlaces de un túnel temporal dejan de funcionar cuando cambia o se detiene el túnel. Use el dominio HTTPS definitivo antes de invitar a clientes reales. Google debe aprobar el acceso de publicación del emisor antes de distribuir pases a clientes que no sean probadores.

Para habilitar Apple Wallet posteriormente, configure credenciales válidas y `APPLE_WALLET_ENABLED=true`, compruebe la generación y actualización con un iPhone real y regenere la caché de configuración si corresponde. Desactivar la emisión no elimina pases Apple previamente instalados ni deshabilita su servicio de actualización.

## Avisos de Google Wallet

Las asignaciones de sellos y los ajustes positivos generan un aviso para los pases Google existentes; si la operación desbloquea una recompensa, se genera un aviso de recompensa en lugar de uno de sellos. El comando `wallet:process` sincroniza primero la tarjeta y después intenta enviar los avisos. Un pase no verificado o una tarjeta cuya sincronización ha fallado no recibe avisos. Los avisos de recompensa pendientes se procesan antes que los de sellos.

Por cada pase, se admiten hasta tres intentos de notificación en una ventana de 24 horas, pero los avisos de sellos solo se envían mientras hay menos de dos intentos en esa ventana. Esto reserva capacidad para recompensas; los avisos adicionales se omiten, no se reprograman. Los envíos hechos directamente desde Google u otros sistemas no aparecen en este contador, y Google puede aplicar sus propios límites. La actualización de la tarjeta es independiente de los avisos.

El estado `sent` indica que Google aceptó la solicitud, no que Android haya mostrado la notificación. Google controla su entrega y el usuario debe tener las notificaciones activadas. Si no se puede confirmar la respuesta, el estado es `uncertain`: se registra el error y se cuenta el intento durante 24 horas, sin reintentar automáticamente para evitar avisos duplicados. Un fallo previo al envío no consume un espacio del contador local: se reintenta con espera creciente y pasa a `failed` al agotar los intentos. Revise los registros de la aplicación y la tabla `google_wallet_notifications`. Si la tarjeta tiene los 10 mensajes permitidos por Google, el sistema solo elimina un mensaje anterior que haya registrado como propio; si no puede hacerlo, registra el fallo.

El procesador usa bloqueos de caché para evitar actualizaciones simultáneas de la misma tarjeta. Todas las instancias deben compartir un almacén de caché compatible con bloqueos atómicos. En WAMP, el procesador temporal y el túnel de prueba deben permanecer activos: no arrancan automáticamente con Windows y solo cubren el negocio seleccionado. Para usuarios reales se necesita un dominio HTTPS estable, el cron del servidor y acceso de publicación aprobado por Google.
