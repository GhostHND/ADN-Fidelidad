<?php

namespace App\Services\Wallet;

use App\Models\Business;
use App\Models\CustomerCard;
use App\Models\LoyaltyProgram;
use App\Models\WalletPass;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class AppleWalletProvider implements WalletProvider
{
    public function __construct(private StampStripRenderer $strip) {}

    public function platform(): string
    {
        return 'apple';
    }

    public function missingConfig(): array
    {
        $c = config('fidelidad.wallet.apple');
        $missing = [];
        foreach (['pass_type_id' => 'APPLE_PASS_TYPE_ID', 'team_id' => 'APPLE_TEAM_ID', 'certificate_password' => 'APPLE_PASS_CERT_PASSWORD'] as $k => $env) {
            if (! $c[$k]) {
                $missing[] = $env;
            }
        }
        foreach (['certificate_path' => 'APPLE_PASS_CERT_PATH', 'wwdr_path' => 'APPLE_WWDR_CERT_PATH'] as $k => $env) {
            if (! $c[$k] || ! is_file($c[$k])) {
                $missing[] = $env.' (archivo no encontrado)';
            }
        }

        return $missing;
    }

    public function isConfigured(): bool
    {
        return $this->missingConfig() === [];
    }

    public function ensurePass(CustomerCard $card): WalletPass
    {
        return WalletPass::firstOrCreate(
            ['card_id' => $card->id, 'platform' => 'apple'],
            ['identifier' => 'adnf-'.$card->uuid, 'auth_token' => Str::random(40)],
        );
    }

    public function installUrl(CustomerCard $card): string
    {
        return url('/tarjeta/'.$card->uuid.'/apple.pkpass');
    }

    public function syncProgram(LoyaltyProgram $program): void
    {
        // Apple no tiene clase compartida: cada pase se actualiza individualmente.
    }

    /** Notifica por APNs a los dispositivos registrados; éstos descargan la nueva versión del pase. */
    public function syncCard(WalletPass $pass): void
    {
        $this->assertConfigured();
        $card = CustomerCard::withoutGlobalScopes()->findOrFail($pass->card_id);
        $registrations = $pass->registrations()->with('device')->get();
        if ($registrations->isEmpty()) {
            return; // el pase todavía no está en ningún dispositivo
        }
        $errors = [];
        foreach ($registrations as $reg) {
            try {
                $this->pushToDevice($reg->device->push_token);
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), '410')) {
                    $reg->delete(); // dispositivo ya no válido
                    continue;
                }
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            throw new \RuntimeException(implode(' | ', $errors));
        }
        $pass->update(['synced_revision' => $card->revision, 'last_synced_at' => now(), 'last_error' => null]);
    }

    private function pushToDevice(string $pushToken): void
    {
        [$certPem, $keyPem] = $this->pemFiles();
        $ch = curl_init('https://api.push.apple.com/3/device/'.$pushToken);
        curl_setopt_array($ch, [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => ['apns-topic: '.config('fidelidad.wallet.apple.pass_type_id'), 'apns-push-type: background'],
            CURLOPT_SSLCERT => $certPem,
            CURLOPT_SSLKEY => $keyPem,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($status !== 200) {
            throw new \RuntimeException("APNs respondió {$status} {$err} {$body}");
        }
    }

    /** @return array{0:string,1:string} rutas temporales PEM (certificado, llave) */
    private function pemFiles(): array
    {
        $creds = $this->loadCredentials();
        $cert = tempnam(sys_get_temp_dir(), 'adnc');
        $key = tempnam(sys_get_temp_dir(), 'adnk');
        file_put_contents($cert, $creds['cert']);
        file_put_contents($key, $creds['pkey']);
        register_shutdown_function(fn () => @unlink($cert) | @unlink($key));

        return [$cert, $key];
    }

    private function loadCredentials(): array
    {
        $p12 = file_get_contents(config('fidelidad.wallet.apple.certificate_path'));
        $out = [];
        if (! openssl_pkcs12_read($p12, $out, (string) config('fidelidad.wallet.apple.certificate_password'))) {
            throw new \RuntimeException('No se pudo abrir el certificado .p12 (contraseña o archivo inválido).');
        }

        return $out;
    }

    public function passJson(CardView $view, WalletPass $pass): array
    {
        $card = $view->card;
        $business = $view->business();
        $d = $view->design();
        $c = config('fidelidad.wallet.apple');

        $json = [
            'formatVersion' => 1,
            'passTypeIdentifier' => $c['pass_type_id'],
            'teamIdentifier' => $c['team_id'],
            'serialNumber' => $pass->identifier,
            'organizationName' => $business->name,
            'description' => $view->program()->name,
            'logoText' => $business->name,
            'foregroundColor' => $this->rgb($d['foreground_color']),
            'backgroundColor' => $this->rgb($d['background_color']),
            'labelColor' => $this->rgb($d['label_color']),
            'webServiceURL' => rtrim(config('app.url'), '/').'/wallet/apple',
            'authenticationToken' => $pass->auth_token,
            'barcodes' => [['format' => 'PKBarcodeFormatQR', 'message' => $card->qrPayload(), 'messageEncoding' => 'iso-8859-1', 'altText' => substr($card->uuid, 0, 8)]],
            'storeCard' => [
                'headerFields' => [['key' => 'balance', 'label' => 'SELLOS', 'value' => $view->progressText(), 'changeMessage' => 'Tus sellos: %@']],
                'secondaryFields' => [
                    ['key' => 'reward', 'label' => 'RECOMPENSA', 'value' => $view->rewardSummary(), 'changeMessage' => '%@'],
                ],
                'auxiliaryFields' => [['key' => 'member', 'label' => 'CLIENTE', 'value' => $card->customer->name]],
                'backFields' => array_values(array_filter([
                    ['key' => 'terms', 'label' => 'Condiciones', 'value' => $view->program()->terms ?: 'Consulta las condiciones con el establecimiento.'],
                    $business->phone ? ['key' => 'phone', 'label' => 'Teléfono', 'value' => $business->phone] : null,
                    $business->address ? ['key' => 'address', 'label' => 'Dirección', 'value' => $business->address] : null,
                    ['key' => 'issuer', 'label' => 'Emitida por', 'value' => $business->name.' · powered by ADN Publicidad'],
                ])),
            ],
        ];
        if (! empty($d['expiration'])) {
            $json['expirationDate'] = $d['expiration'];
        }

        return $json;
    }

    /** Genera el contenido binario del .pkpass firmado. */
    public function buildPkpass(CustomerCard $card): string
    {
        $this->assertConfigured();
        $card = CustomerCard::withoutGlobalScopes()->with(['customer', 'program'])->findOrFail($card->id);
        $pass = $this->ensurePass($card);
        $view = CardView::for($card);

        $files = ['pass.json' => json_encode($this->passJson($view, $pass), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        $files += $this->images($view);

        $manifest = [];
        foreach ($files as $name => $data) {
            $manifest[$name] = sha1($data);
        }
        $manifestJson = json_encode($manifest);
        $files['manifest.json'] = $manifestJson;
        $files['signature'] = $this->sign($manifestJson);

        $tmp = tempnam(sys_get_temp_dir(), 'pkp');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $data) {
            $zip->addFromString($name, $data);
        }
        $zip->close();
        $bin = file_get_contents($tmp);
        @unlink($tmp);

        return $bin;
    }

    private function images(CardView $view): array
    {
        $business = $view->business();
        $files = [];
        $files['strip@2x.png'] = $this->strip->render($view, 750, 196);
        $files['strip.png'] = $this->strip->render($view, 375, 98);

        $logo = $business->logo_path && Storage::disk('public')->exists($business->logo_path)
            ? @imagecreatefromstring(Storage::disk('public')->get($business->logo_path)) : null;
        foreach ([['', 1], ['@2x', 2]] as [$suffix, $scale]) {
            $files['icon'.$suffix.'.png'] = $this->resizePng($logo, 29 * $scale, 29 * $scale, $business);
            $files['logo'.$suffix.'.png'] = $this->resizePng($logo, 160 * $scale, 50 * $scale, $business, true);
        }

        return $files;
    }

    private function resizePng($src, int $w, int $h, Business $business, bool $keepRatio = false): string
    {
        $dst = imagecreatetruecolor($w, $h);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        if ($src) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $ratio = min($w / $sw, $h / $sh);
            $nw = $keepRatio ? (int) ($sw * $ratio) : $w;
            $nh = $keepRatio ? (int) ($sh * $ratio) : $h;
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
        } else {
            [$r, $g, $b] = sscanf($business->primary_color, '#%02x%02x%02x');
            imagefilledrectangle($dst, 0, 0, $w, $h, imagecolorallocate($dst, $r ?? 15, $g ?? 167, $b ?? 180));
        }
        ob_start();
        imagepng($dst);

        return ob_get_clean();
    }

    private function sign(string $manifest): string
    {
        $creds = $this->loadCredentials();
        $in = tempnam(sys_get_temp_dir(), 'man');
        $out = tempnam(sys_get_temp_dir(), 'sig');
        file_put_contents($in, $manifest);
        $ok = openssl_pkcs7_sign($in, $out, $creds['cert'], [$creds['pkey'], ''], [], PKCS7_BINARY | PKCS7_DETACHED, config('fidelidad.wallet.apple.wwdr_path'));
        $smime = file_get_contents($out);
        @unlink($in);
        @unlink($out);
        if (! $ok) {
            throw new \RuntimeException('Falló la firma del pase: '.openssl_error_string());
        }
        // Extrae el cuerpo base64 (DER) de la salida S/MIME.
        $parts = preg_split('/\r?\n\r?\n/', $smime, 2);
        $body = $parts[1] ?? '';
        $body = preg_replace('/\r?\n------.*$/s', '', $body);

        return base64_decode(trim($body));
    }

    private function rgb(string $hex): string
    {
        [$r, $g, $b] = sscanf(strlen($hex) === 7 ? $hex : '#000000', '#%02x%02x%02x');

        return "rgb($r,$g,$b)";
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Apple Wallet no está configurado: '.implode(', ', $this->missingConfig()));
        }
    }
}
