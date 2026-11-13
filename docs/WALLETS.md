# Apple Wallet y Google Wallet

## Apple
1. En Apple Developer crear un **Pass Type ID** y su certificado; exportar `.p12` con contraseña.
2. Descargar el certificado WWDR e convertirlo a PEM.
3. Configurar `APPLE_PASS_TYPE_ID`, `APPLE_TEAM_ID`, `APPLE_PASS_CERT_PATH`, `APPLE_PASS_CERT_PASSWORD`, `APPLE_WWDR_CERT_PATH`.
4. El `webServiceURL` apunta a `/wallet/apple/v1` del dominio (HTTPS obligatorio).

## Google
1. Crear cuenta de emisor en Google Wallet API, obtener `issuer_id`.
2. Crear cuenta de servicio con acceso al emisor; guardar el JSON fuera de `public/`.
3. Configurar `GOOGLE_WALLET_ISSUER_ID`, `GOOGLE_WALLET_SERVICE_ACCOUNT_JSON`, `GOOGLE_WALLET_ORIGINS`.

Si falta configuración, los botones de wallet **no se muestran** (no hay botones simulados).
