<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\CustomerCard;
use App\Models\LoyaltyCycle;
use App\Models\LoyaltyProgram;
use App\Models\Reward;
use App\Models\RewardEntitlement;
use App\Models\RewardRedemption;
use App\Models\StampRule;
use App\Models\StampTransaction;
use App\Models\User;
use App\Services\Wallet\GoogleWalletNotifications;
use App\Services\Wallet\WalletSyncQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Reglas de sellos, ciclos, recompensas y canjes. Toda la aritmética de saldos ocurre aquí, en el servidor. */
class LoyaltyService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private WalletSyncQueue $wallet,
        private GoogleWalletNotifications $notifications,
    ) {}

    /** Resuelve el cliente/tarjeta a partir del contenido del QR. Sólo del negocio indicado. */
    public function findCardByQr(Business $business, string $payload): CustomerCard
    {
        $token = str_starts_with($payload, 'ADNF:') ? substr($payload, 5) : trim($payload);
        $card = CustomerCard::withoutGlobalScopes()->where('qr_token', $token)->first();
        if (! $card) {
            throw new LoyaltyException('No se reconoce este código. Verifica que sea una tarjeta de ADN Fidelidad.', 404);
        }
        if ($card->business_id !== $business->id) {
            throw new LoyaltyException('Esta tarjeta pertenece a otro negocio.', 403);
        }

        return $card;
    }

    /**
     * Calcula los sellos a otorgar. Nunca confía en un total enviado por el navegador:
     * la entrada son datos de la operación y el servidor deriva y limita la cantidad.
     */
    public function resolveStamps(LoyaltyProgram $program, array $input): int
    {
        $stamps = match ($program->rule_type) {
            'per_amount' => $this->stampsFromAmount($program, (float) ($input['amount'] ?? 0)),
            'per_service', 'promotional' => $this->stampsFromRule($program, (int) ($input['rule_id'] ?? 0)),
            default => (int) ($input['quantity'] ?? 1),
        };
        if ($stamps < 1 || $stamps > $program->max_stamps_per_operation) {
            throw new LoyaltyException('No se puede asignar esa cantidad de sellos.');
        }

        return $stamps;
    }

    private function stampsFromAmount(LoyaltyProgram $program, float $amount): int
    {
        if (! $program->amount_per_stamp || (float) $program->amount_per_stamp <= 0 || $amount <= 0) {
            throw new LoyaltyException('Indica un monto de consumo válido.');
        }

        return (int) floor($amount / (float) $program->amount_per_stamp);
    }

    private function stampsFromRule(LoyaltyProgram $program, int $ruleId): int
    {
        $rule = StampRule::where('program_id', $program->id)->find($ruleId);
        if (! $rule || ! $rule->isCurrent()) {
            throw new LoyaltyException('La regla o promoción seleccionada no está vigente.');
        }

        return $rule->stamps;
    }

    /**
     * @param  array{quantity?:int,amount?:float,rule_id?:int,reference?:string,reason?:string}  $input
     * @return array{transaction:StampTransaction, card:CustomerCard, duplicate:bool, new_entitlements:int}
     */
    public function award(CustomerCard $card, User $employee, ?int $branchId, array $input, string $idempotencyKey): array
    {
        $business = Business::findOrFail($card->business_id);
        $this->assertOperable($business);

        if ($branchId && ! BusinessBranch::withoutGlobalScopes()->where('business_id', $business->id)->where('is_active', true)->whereKey($branchId)->exists()) {
            throw new LoyaltyException('La sucursal no pertenece a este negocio.', 403);
        }

        return DB::transaction(function () use ($card, $employee, $branchId, $input, $idempotencyKey, $business) {
            if ($existing = StampTransaction::withoutGlobalScopes()->where('idempotency_key', $idempotencyKey)->first()) {
                if ($existing->card_id !== $card->id) {
                    throw new LoyaltyException('Clave de operación en conflicto.', 409);
                }

                return ['transaction' => $existing, 'card' => $card->refresh(), 'duplicate' => true, 'new_entitlements' => 0];
            }

            $locked = CustomerCard::withoutGlobalScopes()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            $program = LoyaltyProgram::withoutGlobalScopes()->findOrFail($locked->program_id);

            if ($locked->status !== 'active') {
                throw new LoyaltyException('La tarjeta no está activa.');
            }
            if (! $program->isOpen()) {
                throw new LoyaltyException('La promoción ha vencido o no está activa.');
            }

            $stamps = $this->resolveStamps($program, $input);

            $tx = $this->writeTransaction($locked, $program, 'earn', $stamps, [
                'user_id' => $employee->id,
                'branch_id' => $branchId,
                'purchase_amount' => $input['amount'] ?? null,
                'reference' => $input['reference'] ?? null,
                'reason' => $input['reason'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ], lifetime: $stamps);

            $created = $this->evaluateRewards($locked, $program);
            $this->applyCyclePolicy($locked, $program, $employee->id, $branchId);

            $locked->forceFill(['last_activity_at' => now()])->save();
            $this->touchCustomerActivity($locked);
            $this->bump($locked, 'stamp');
            $this->notifications->queue($locked, $tx, $created > 0);

            AuditLog::record('stamps.assigned', $business, $tx, ['stamps' => $stamps, 'card' => $locked->uuid], $employee);

            return ['transaction' => $tx, 'card' => $locked->refresh(), 'duplicate' => false, 'new_entitlements' => $created];
        });
    }

    /** Revierte un movimiento mediante una transacción nueva relacionada; nunca borra el original. */
    public function reverse(StampTransaction $original, User $user, string $reason): StampTransaction
    {
        $business = Business::findOrFail($original->business_id);
        $this->assertOperable($business);

        return DB::transaction(function () use ($original, $user, $reason, $business) {
            $card = CustomerCard::withoutGlobalScopes()->whereKey($original->card_id)->lockForUpdate()->firstOrFail();
            $program = LoyaltyProgram::withoutGlobalScopes()->findOrFail($card->program_id);

            if ($original->type !== 'earn') {
                throw new LoyaltyException('Sólo se pueden revertir asignaciones de sellos.');
            }
            if (StampTransaction::withoutGlobalScopes()->where('reverses_id', $original->id)->exists()) {
                throw new LoyaltyException('Este movimiento ya fue revertido.');
            }
            if ($original->cycle_number !== $card->cycle_number || $card->stamps_balance < $original->stamps) {
                throw new LoyaltyException('No se puede revertir: el ciclo ya avanzó o el saldo actual es insuficiente.');
            }

            $tx = $this->writeTransaction($card, $program, 'reverse', -$original->stamps, [
                'user_id' => $user->id,
                'branch_id' => $original->branch_id,
                'reason' => $reason,
                'reverses_id' => $original->id,
                'idempotency_key' => 'reverse-'.$original->uuid,
            ], lifetime: -$original->stamps);

            // Derechos aún no canjeados que dependían de los sellos revertidos dejan de ser válidos.
            RewardEntitlement::withoutGlobalScopes()
                ->where('card_id', $card->id)->where('cycle_number', $card->cycle_number)->where('status', 'available')
                ->whereHas('reward', fn ($q) => $q->withoutGlobalScopes()->where('stamps_required', '>', $card->stamps_balance))
                ->update(['status' => 'void']);

            $this->bump($card, 'reverse');
            AuditLog::record('stamps.reversed', $business, $tx, ['original' => $original->uuid, 'reason' => $reason], $user);

            return $tx;
        });
    }

    public function redeem(RewardEntitlement $entitlement, User $employee, ?int $branchId): RewardRedemption
    {
        $business = Business::findOrFail($entitlement->business_id);
        $this->assertOperable($business);

        return DB::transaction(function () use ($entitlement, $employee, $branchId, $business) {
            $ent = RewardEntitlement::withoutGlobalScopes()->whereKey($entitlement->id)->lockForUpdate()->firstOrFail();
            if ($ent->status !== 'available') {
                throw new LoyaltyException('La recompensa ya fue canjeada o dejó de estar disponible.', 409);
            }
            if ($ent->expires_at && $ent->expires_at->isPast()) {
                $ent->update(['status' => 'expired']);
                throw new LoyaltyException('La recompensa ha vencido.');
            }
            $reward = Reward::withoutGlobalScopes()->findOrFail($ent->reward_id);
            if (! $reward->isOpen()) {
                throw new LoyaltyException('La recompensa ha vencido o fue desactivada.');
            }
            if ($reward->max_redemptions !== null
                && RewardRedemption::withoutGlobalScopes()->whereHas('entitlement', fn ($q) => $q->withoutGlobalScopes()->where('reward_id', $reward->id))->count() >= $reward->max_redemptions) {
                throw new LoyaltyException('Se alcanzó el máximo de canjes de esta recompensa.');
            }

            $card = CustomerCard::withoutGlobalScopes()->whereKey($ent->card_id)->lockForUpdate()->firstOrFail();
            $program = LoyaltyProgram::withoutGlobalScopes()->findOrFail($card->program_id);

            // Con política "reset"/"keep_remainder" los sellos ya se descontaron al cerrar el ciclo; solo se descuentan aquí si el ciclo sigue abierto.
            if ($reward->consumes_stamps && (int) $ent->cycle_number === (int) $card->cycle_number) {
                if ($card->stamps_balance < $reward->stamps_required) {
                    throw new LoyaltyException('El saldo de sellos ya no alcanza para este canje.');
                }
                $this->writeTransaction($card, $program, 'consume', -$reward->stamps_required, [
                    'user_id' => $employee->id,
                    'branch_id' => $branchId,
                    'reason' => 'Canje: '.$reward->name,
                    'idempotency_key' => 'redeem-'.$ent->uuid,
                ]);
            }

            $ent->update(['status' => 'redeemed']);
            $redemption = RewardRedemption::withoutGlobalScopes()->create([
                'business_id' => $ent->business_id,
                'entitlement_id' => $ent->id,
                'user_id' => $employee->id,
                'branch_id' => $branchId,
                'redeemed_at' => now(),
            ]);

            $card->forceFill(['last_activity_at' => now()])->save();
            $this->touchCustomerActivity($card);
            $this->bump($card, 'redeem');
            AuditLog::record('reward.redeemed', $business, $redemption, ['reward' => $reward->name, 'card' => $card->uuid], $employee);

            return $redemption;
        });
    }

    /** Corrección administrativa (sellos positivos o negativos), siempre auditada y como movimiento nuevo. */
    public function adjust(CustomerCard $card, User $user, int $stamps, string $reason): StampTransaction
    {
        $business = Business::findOrFail($card->business_id);
        $this->assertOperable($business);
        if ($stamps === 0 || trim($reason) === '') {
            throw new LoyaltyException('Indica una cantidad distinta de cero y el motivo de la corrección.');
        }

        return DB::transaction(function () use ($card, $user, $stamps, $reason, $business) {
            $locked = CustomerCard::withoutGlobalScopes()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            $program = LoyaltyProgram::withoutGlobalScopes()->findOrFail($locked->program_id);
            if ($locked->stamps_balance + $stamps < 0) {
                throw new LoyaltyException('La corrección dejaría el saldo en negativo.');
            }
            $tx = $this->writeTransaction($locked, $program, 'adjust', $stamps, [
                'user_id' => $user->id,
                'reason' => $reason,
                'idempotency_key' => 'adjust-'.Str::uuid(),
            ], lifetime: max($stamps, 0));
            $created = $this->evaluateRewards($locked, $program);
            $this->bump($locked, 'adjust');
            if ($stamps > 0) {
                $this->notifications->queue($locked, $tx, $created > 0);
            }
            AuditLog::record('stamps.adjusted', $business, $tx, ['stamps' => $stamps, 'reason' => $reason], $user);

            return $tx;
        });
    }

    private function writeTransaction(CustomerCard $card, LoyaltyProgram $program, string $type, int $stamps, array $attrs, int $lifetime = 0): StampTransaction
    {
        $balance = $card->stamps_balance + $stamps;
        $tx = StampTransaction::withoutGlobalScopes()->create($attrs + [
            'business_id' => $card->business_id,
            'card_id' => $card->id,
            'customer_id' => $card->customer_id,
            'program_id' => $program->id,
            'type' => $type,
            'stamps' => $stamps,
            'balance_after' => $balance,
            'cycle_number' => $card->cycle_number,
        ]);
        $card->stamps_balance = $balance;
        $card->lifetime_stamps = max(0, $card->lifetime_stamps + $lifetime);
        $card->save();
        LoyaltyCycle::where('card_id', $card->id)->where('number', $card->cycle_number)->update(['stamps' => $balance]);

        return $tx;
    }

    /** Crea derechos (una sola vez por tarjeta, recompensa y ciclo) al alcanzar cada meta. */
    private function evaluateRewards(CustomerCard $card, LoyaltyProgram $program): int
    {
        $created = 0;
        $rewards = Reward::withoutGlobalScopes()->where('program_id', $program->id)->where('status', 'active')
            ->where('stamps_required', '<=', $card->stamps_balance)->get();

        foreach ($rewards as $reward) {
            if (! $reward->isOpen()) {
                continue;
            }
            $ent = RewardEntitlement::withoutGlobalScopes()->firstOrCreate(
                ['card_id' => $card->id, 'reward_id' => $reward->id, 'cycle_number' => $card->cycle_number],
                ['business_id' => $card->business_id, 'earned_at' => now(), 'expires_at' => $reward->valid_until],
            );
            $created += $ent->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /** Al llegar a la meta del programa, cierra el ciclo según la política configurada. */
    private function applyCyclePolicy(CustomerCard $card, LoyaltyProgram $program, ?int $userId, ?int $branchId): void
    {
        if ($program->cycle_policy === 'continue' || $card->stamps_balance < $program->stamps_goal) {
            return;
        }
        $clear = $program->cycle_policy === 'reset' ? $card->stamps_balance : $program->stamps_goal;
        $this->writeTransaction($card, $program, 'consume', -$clear, [
            'user_id' => $userId,
            'branch_id' => $branchId,
            'reason' => 'Ciclo completado',
            'idempotency_key' => 'cycle-'.$card->uuid.'-'.$card->cycle_number,
        ]);
        LoyaltyCycle::where('card_id', $card->id)->where('number', $card->cycle_number)->update(['completed_at' => now()]);
        $card->cycle_number++;
        $card->save();
        LoyaltyCycle::create(['card_id' => $card->id, 'number' => $card->cycle_number, 'stamps' => $card->stamps_balance, 'started_at' => now()]);
        // Si el remanente ya alcanza metas del nuevo ciclo, generarlas.
        $this->evaluateRewards($card, $program);
    }

    private function bump(CustomerCard $card, string $reason): void
    {
        $card->increment('revision');
        $this->wallet->queueCard($card->refresh(), $reason);
    }

    private function touchCustomerActivity(CustomerCard $card): void
    {
        DB::table('customer_business')
            ->where('business_id', $card->business_id)->where('customer_id', $card->customer_id)
            ->update(['last_activity_at' => now()]);
    }

    private function assertOperable(Business $business): void
    {
        if (! $this->subscriptions->canOperate($business)) {
            throw new LoyaltyException('La suscripción no permite esta funcionalidad.', 402);
        }
    }

    /** Crea tarjeta + primer ciclo para un cliente en un programa (idempotente). */
    public function issueCard(Business $business, LoyaltyProgram $program, Customer $customer): CustomerCard
    {
        return DB::transaction(function () use ($business, $program, $customer) {
            $card = CustomerCard::withoutGlobalScopes()->where('customer_id', $customer->id)->where('program_id', $program->id)->first();
            if ($card) {
                return $card;
            }
            $card = CustomerCard::withoutGlobalScopes()->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'program_id' => $program->id,
                'program_version' => $program->current_version ?? 1,
            ]);
            LoyaltyCycle::create(['card_id' => $card->id, 'number' => 1, 'stamps' => 0, 'started_at' => now()]);
            AuditLog::record('card.issued', $business, $card, ['program' => $program->name], null);

            return $card;
        });
    }
}
