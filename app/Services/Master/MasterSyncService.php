<?php

namespace App\Services\Master;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\MasterSyncEvent;
use App\Models\SubscriptionEntitlement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Adaptador de Software Master. Contrato v1 (ver docs/API_SOFTWARE_MASTER.md):
 * recibe/consulta el estado de la suscripción y lo guarda como "entitlement" local de sólo lectura comercial.
 */
class MasterSyncService
{
    public const STATUSES = ['active', 'trial', 'past_due', 'suspended', 'expired', 'cancelled'];

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:100'],
            'event_type' => ['required', 'string', 'max:60'],
            'event_version' => ['required', 'integer', 'min:0'],
            'product_code' => ['required', 'in:'.config('fidelidad.product_code')],
            'business.master_business_id' => ['required', 'string', 'max:100'],
            'subscription.id' => ['required', 'string', 'max:100'],
            'subscription.plan' => ['required', 'string', 'max:100'],
            'subscription.status' => ['required', 'in:'.implode(',', self::STATUSES)],
            'subscription.starts_at' => ['nullable', 'date'],
            'subscription.ends_at' => ['nullable', 'date'],
            'subscription.features' => ['present', 'array'],
            'subscription.features.*' => ['string', 'in:'.implode(',', config('fidelidad.plan_features'))],
            'subscription.limits' => ['present', 'array'],
            'subscription.limits.*' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array{status:string, message:string, http:int} */
    public function handle(array $payload): array
    {
        $validator = Validator::make($payload, $this->rules());
        if ($validator->fails()) {
            $this->log($payload, 'rejected', $validator->errors()->first());

            return ['status' => 'rejected', 'message' => $validator->errors()->first(), 'http' => 422];
        }

        $eventId = $payload['event_id'];
        $masterId = $payload['business']['master_business_id'];

        $business = Business::where('master_business_id', $masterId)->first();
        if (! $business) {
            $this->log($payload, 'failed', 'Negocio no vinculado a este identificador.');

            return ['status' => 'failed', 'message' => 'Negocio no vinculado. Cree primero el negocio en ADN Fidelidad.', 'http' => 404];
        }

        try {
            $event = MasterSyncEvent::create([
                'event_id' => $eventId, 'type' => $payload['event_type'], 'master_business_id' => $masterId,
                'event_version' => $payload['event_version'], 'status' => 'received', 'payload' => $payload,
            ]);
        } catch (QueryException $e) {
            if (! MasterSyncEvent::query()->where('event_id', $eventId)->exists()) {
                throw $e;
            }

            return ['status' => 'duplicate', 'message' => 'Evento ya procesado.', 'http' => 200];
        }

        return DB::transaction(function () use ($payload, $business, $event) {
            $current = SubscriptionEntitlement::where('business_id', $business->id)->lockForUpdate()->first();
            if ($current && $payload['event_version'] <= $current->event_version) {
                $event->update(['status' => 'ignored', 'error' => 'Versión obsoleta.']);

                return ['status' => 'ignored', 'message' => 'Evento con versión obsoleta.', 'http' => 200];
            }
            $s = $payload['subscription'];
            SubscriptionEntitlement::updateOrCreate(['business_id' => $business->id], [
                'product_code' => $payload['product_code'],
                'subscription_id' => $s['id'],
                'plan' => $s['plan'],
                'status' => $s['status'],
                'starts_at' => $s['starts_at'] ?? null,
                'ends_at' => $s['ends_at'] ?? null,
                'features' => array_values($s['features']),
                'limits' => $s['limits'],
                'event_version' => $payload['event_version'],
                'last_synced_at' => now(),
            ]);
            $event->update(['status' => 'applied']);
            AuditLog::record('master.entitlement_applied', $business, null, ['plan' => $s['plan'], 'status' => $s['status'], 'version' => $payload['event_version']], null);

            return ['status' => 'applied', 'message' => 'Suscripción sincronizada.', 'http' => 200];
        });
    }

    /** Consulta activa (respaldo del webhook). Firma igual que el resto de integraciones de ADN. */
    public function pull(Business $business): array
    {
        $base = rtrim((string) config('fidelidad.master.api_url'), '/');
        $secret = (string) config('fidelidad.master.api_secret');
        if ($base === '' || $secret === '' || ! $business->master_business_id) {
            return ['status' => 'unconfigured', 'message' => 'Integración con Software Master sin configurar.', 'http' => 503];
        }
        $path = '/api/v1/integrations/'.config('fidelidad.product_code').'/subscriptions/'.rawurlencode($business->master_business_id);
        $ts = (string) now()->timestamp;
        $key = (string) Str::uuid();
        $signature = hash_hmac('sha256', $ts."\n".$key."\n", $secret);

        $res = Http::acceptJson()->timeout(15)->retry(2, 500, throw: false)->withHeaders([
            'X-ADN-Timestamp' => $ts, 'X-ADN-Idempotency-Key' => $key, 'X-ADN-Signature' => $signature,
            'Authorization' => 'Bearer '.config('fidelidad.master.api_key'),
        ])->get($base.$path);

        if (! $res->successful()) {
            $this->log(['event_id' => 'pull-'.$key, 'event_type' => 'pull.failed', 'business' => ['master_business_id' => $business->master_business_id]], 'failed', 'HTTP '.$res->status());

            return ['status' => 'failed', 'message' => 'Software Master respondió '.$res->status().'. Se conserva el último estado sincronizado.', 'http' => 502];
        }

        return $this->handle($res->json());
    }

    private function log(array $payload, string $status, string $error): void
    {
        MasterSyncEvent::create([
            'event_id' => $status.'-'.Str::uuid(),
            'type' => (string) ($payload['event_type'] ?? 'unknown'),
            'master_business_id' => $payload['business']['master_business_id'] ?? null,
            'event_version' => is_numeric($payload['event_version'] ?? null) ? (int) $payload['event_version'] : null,
            'status' => $status, 'payload' => $payload, 'error' => $error,
        ]);
    }
}
