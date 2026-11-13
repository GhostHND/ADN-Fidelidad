<?php

namespace App\Services\Wallet;

use App\Models\Business;
use App\Models\CustomerCard;
use App\Models\GoogleWalletNotification;
use App\Models\LoyaltyProgram;
use App\Models\WalletPass;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleWalletProvider implements WalletProvider
{
    private const API = 'https://walletobjects.googleapis.com/walletobjects/v1';

    public function platform(): string
    {
        return 'google';
    }

    public function missingConfig(): array
    {
        $missing = [];
        if (! config('fidelidad.wallet.google.issuer_id')) {
            $missing[] = 'GOOGLE_WALLET_ISSUER_ID';
        }
        $path = config('fidelidad.wallet.google.service_account_json');
        if (! $path || ! is_file($path)) {
            $missing[] = 'GOOGLE_WALLET_SERVICE_ACCOUNT_JSON (archivo no encontrado)';
        }

        return $missing;
    }

    public function isConfigured(): bool
    {
        return $this->missingConfig() === [];
    }

    private function issuer(): string
    {
        return (string) config('fidelidad.wallet.google.issuer_id');
    }

    public function classId(LoyaltyProgram $program): string
    {
        return $this->issuer().'.adnf_prog_'.str_replace('-', '', $program->uuid);
    }

    public function objectId(CustomerCard $card): string
    {
        return $this->issuer().'.adnf_card_'.str_replace('-', '', $card->uuid);
    }

    public function ensurePass(CustomerCard $card): WalletPass
    {
        return WalletPass::firstOrCreate(
            ['card_id' => $card->id, 'platform' => 'google'],
            ['identifier' => $this->objectId($card)],
        );
    }

    public function installUrl(CustomerCard $card): string
    {
        $this->assertConfigured();
        $program = LoyaltyProgram::withoutGlobalScopes()->findOrFail($card->program_id);
        $this->upsertClass($program);

        $credentials = $this->credentials();
        $claims = [
            'iss' => $credentials['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'origins' => config('fidelidad.wallet.google.origins') ?: [rtrim(config('app.url'), '/')],
            'payload' => ['loyaltyObjects' => [$this->objectPayload($card)]],
        ];

        return 'https://pay.google.com/gp/v/save/'.JWT::encode($claims, $credentials['private_key'], 'RS256');
    }

    public function syncCard(WalletPass $pass): void
    {
        $this->assertConfigured();
        $card = CustomerCard::withoutGlobalScopes()->findOrFail($pass->card_id);
        $payload = $this->objectPayload($card);
        $id = rawurlencode($pass->identifier);

        $res = $this->request('PATCH', "/loyaltyObject/{$id}", $payload);
        if ($res->status() === 404) {
            // Aún no fue guardada en el teléfono: no hay objeto remoto que actualizar.
            $pass->update(['last_error' => null, 'status' => 'link_opened', 'verified_at' => null]);

            return;
        }
        if (! $res->successful()) {
            throw new \RuntimeException('Google Wallet respondió '.$res->status().': '.$res->body());
        }
        $pass->update(['synced_revision' => $card->revision, 'last_synced_at' => now(), 'last_error' => null, 'status' => 'installed_verified', 'verified_at' => $pass->verified_at ?? now()]);
    }

    public function syncProgram(LoyaltyProgram $program): void
    {
        $this->assertConfigured();
        $this->upsertClass($program);
    }

    public function sendNotification(WalletPass $pass, GoogleWalletNotification $notification): void
    {
        try {
            $this->assertConfigured();
            $id = rawurlencode($pass->identifier);
            $response = $this->request('GET', "/loyaltyObject/{$id}", []);
            if (! $response->successful()) {
                throw new \RuntimeException('No se pudieron consultar los mensajes de Google Wallet: '.$response->status());
            }
            $messages = $response->json('messages', []);
            if (! is_array($messages)) {
                throw new \RuntimeException('Google Wallet devolvió mensajes inválidos.');
            }
            if (count($messages) >= 10) {
                $ownedIds = GoogleWalletNotification::where('wallet_pass_id', $pass->id)
                    ->whereIn('status', ['sent', 'uncertain'])->pluck('id')
                    ->map(fn ($value) => 'adnf_n_'.$value)->all();
                foreach ($messages as $index => $message) {
                    if (in_array($message['id'] ?? null, $ownedIds, true)) {
                        unset($messages[$index]);
                        break;
                    }
                }
                if (count($messages) >= 10) {
                    throw new \RuntimeException('La tarjeta ya tiene 10 mensajes y no hay mensajes de ADN que se puedan retirar.');
                }
                $response = $this->request('PATCH', "/loyaltyObject/{$id}", ['messages' => array_values($messages)]);
                if (! $response->successful()) {
                    throw new \RuntimeException('No se pudo liberar espacio para mensajes en Google Wallet: '.$response->status());
                }
            }
        } catch (\Throwable $e) {
            throw new GoogleWalletNotificationPreflightException($e->getMessage(), 0, $e);
        }

        $reward = $notification->kind === 'reward';
        $response = $this->request('POST', "/loyaltyObject/{$id}/addMessage", ['message' => [
            'id' => 'adnf_n_'.$notification->id,
            'header' => $reward ? 'Recompensa disponible' : 'Sellos actualizados',
            'body' => $reward ? 'Tienes una recompensa disponible. Abre tu tarjeta para consultar los detalles.' : 'Tus sellos se han actualizado. Abre tu tarjeta para consultar tu progreso.',
            'messageType' => 'TEXT_AND_NOTIFY',
        ]]);
        if (! $response->successful()) {
            throw new \RuntimeException('Google Wallet no confirmó la notificación: '.$response->status().' '.mb_substr($response->body(), 0, 500));
        }
    }

    private function upsertClass(LoyaltyProgram $program): void
    {
        $payload = $this->classPayload($program);
        $id = rawurlencode($payload['id']);
        $res = $this->request('PATCH', "/loyaltyClass/{$id}", $payload);
        if ($res->status() === 404) {
            $res = $this->request('POST', '/loyaltyClass', $payload);
        }
        if (! $res->successful()) {
            throw new \RuntimeException('Google Wallet (clase) respondió '.$res->status().': '.$res->body());
        }
    }

    public function classPayload(LoyaltyProgram $program): array
    {
        $business = Business::findOrFail($program->business_id);
        $design = $program->design ?? [];
        $payload = [
            'id' => $this->classId($program),
            'issuerName' => $business->name,
            'programName' => $program->name,
            'reviewStatus' => 'UNDER_REVIEW',
            'hexBackgroundColor' => $design['background_color'] ?? $business->secondary_color,
            'countryCode' => 'HN',
        ];
        if ($logo = $business->logoUrl()) {
            $payload['programLogo'] = [
                'sourceUri' => ['uri' => url($logo)],
                'contentDescription' => ['defaultValue' => ['language' => 'es', 'value' => $business->name]],
            ];
        }

        return $payload;
    }

    public function objectPayload(CustomerCard $card): array
    {
        $card = CustomerCard::withoutGlobalScopes()->with(['customer', 'program'])->findOrFail($card->id);
        $view = CardView::for($card);

        return [
            'id' => $this->objectId($card),
            'classId' => $this->classId($card->program),
            'state' => $card->status === 'active' ? 'ACTIVE' : 'INACTIVE',
            'accountId' => $card->uuid,
            'accountName' => $card->customer->name,
            'barcode' => ['type' => 'QR_CODE', 'value' => $card->qrPayload(), 'alternateText' => substr($card->uuid, 0, 8)],
            'loyaltyPoints' => ['label' => 'Sellos', 'balance' => ['string' => $view->progressText()]],
            'secondaryLoyaltyPoints' => ['label' => 'Recompensa', 'balance' => ['string' => $view->rewardSummary()]],
            'heroImage' => [
                'sourceUri' => ['uri' => $view->stripUrl()],
                'contentDescription' => ['defaultValue' => ['language' => 'es', 'value' => 'Progreso de sellos '.$view->progressText()]],
            ],
        ];
    }

    private function request(string $method, string $path, array $body)
    {
        $options = $method === 'GET' ? [] : ['json' => $body];

        return Http::withToken($this->accessToken())->acceptJson()->timeout(20)->send($method, self::API.$path, $options);
    }

    private function accessToken(): string
    {
        return Cache::remember('google_wallet_token', 3000, function () {
            $c = $this->credentials();
            $now = time();
            $assertion = JWT::encode([
                'iss' => $c['client_email'],
                'scope' => 'https://www.googleapis.com/auth/wallet_object.issuer',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $c['private_key'], 'RS256');

            $res = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);
            if (! $res->successful()) {
                throw new \RuntimeException('No se pudo autenticar con Google: '.$res->status());
            }

            return $res->json('access_token');
        });
    }

    private function credentials(): array
    {
        $c = json_decode((string) file_get_contents(config('fidelidad.wallet.google.service_account_json')), true);
        if (! is_array($c) || empty($c['client_email']) || empty($c['private_key'])) {
            throw new \RuntimeException('La cuenta de servicio de Google Wallet es inválida.');
        }

        return $c;
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Google Wallet no está configurado: '.implode(', ', $this->missingConfig()));
        }
    }
}
