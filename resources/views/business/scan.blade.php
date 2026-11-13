@extends('layouts.app')
@section('title', 'Escanear')
@section('content')
<x-page-header title="Escanear tarjeta" subtitle="Escanea el QR del cliente con la cámara, con un lector USB o escribe el código." />
@php
    $scanConfig = ['lookupUrl' => route('app.scan.lookup'), 'awardUrl' => route('app.scan.award'), 'redeemUrl' => route('app.scan.redeem'), 'branch' => $fixedBranch];
@endphp
<div class="mx-auto max-w-xl" x-data='scanner(@json($scanConfig))' @beforeunload.window="stop()">
    @if ($branches->isNotEmpty() && ! $fixedBranch)
        <div class="mb-3"><label class="label" for="branch">Sucursal</label>
            <select id="branch" class="input" x-model="branch"><option value="">Sin sucursal</option>@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
    @endif

    <div role="status" aria-live="polite" class="mb-3 text-sm font-medium" :class="state === 'error' ? 'text-red-600' : 'text-emerald-700 dark:text-emerald-400'" x-text="message"></div>

    <div x-show="state === 'idle' || state === 'scanning' || state === 'error'" class="card space-y-3">
        <div id="reader" class="overflow-hidden rounded-lg" x-show="state === 'scanning'"></div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-primary" @click="start()" x-show="state !== 'scanning'">Activar cámara</button>
            <button type="button" class="btn-secondary" @click="stop().then(() => state = 'idle')" x-show="state === 'scanning'">Detener cámara</button>
        </div>
        <form @submit.prevent="submitManual()" class="flex gap-2">
            <input x-model="manual" class="input" placeholder="Lector USB o código manual (ADNF:…)" aria-label="Código de la tarjeta" autocomplete="off" autofocus>
            <button class="btn-secondary">Buscar</button>
        </form>
    </div>

    <div x-show="state === 'loading'" x-cloak class="card text-center text-sm">Buscando tarjeta…</div>

    <template x-if="card && (state === 'found' || state === 'done')">
        <div class="card space-y-4">
            <div>
                <div class="text-lg font-bold" x-text="card.customer"></div>
                <div class="text-sm text-neutral-500"><span x-text="card.program"></span> · <b x-text="card.stamps + ' / ' + card.goal"></b> sellos</div>
            </div>

            <div x-show="rewardEarned" x-cloak role="alert" class="rounded-lg border-2 border-amber-500 bg-amber-50 p-4 text-amber-950 dark:border-amber-400 dark:bg-amber-950 dark:text-amber-100">
                <div class="text-lg font-bold">¡El cliente ganó una recompensa!</div>
                <p class="mt-1 text-sm">La recompensa está lista para canjear. Revisa la lista de abajo.</p>
            </div>

            <div x-show="cycleWithoutReward" x-cloak role="alert" class="rounded-lg border-2 border-red-500 bg-red-50 p-4 text-red-950 dark:border-red-400 dark:bg-red-950 dark:text-red-100">
                <div class="text-lg font-bold">Ciclo completado sin recompensa</div>
                <p class="mt-1 text-sm">No se generó una nueva recompensa. Revisa la configuración y vigencia del programa.</p>
            </div>

            <template x-if="card.rewards.length">
                <div class="space-y-2 rounded-lg border border-brand p-3">
                    <div class="text-sm font-semibold">Recompensas disponibles</div>
                    <template x-for="r in card.rewards" :key="r.id">
                        <div class="flex items-center justify-between gap-2 text-sm"><span x-text="r.name"></span>
                            <button type="button" class="btn-primary !min-h-8" :disabled="busy" @click="redeem(r.id)">Canjear</button></div>
                    </template>
                </div>
            </template>

            <div x-show="state === 'found' && card.open && card.status === 'active'" class="space-y-3">
                <template x-if="card.rule_type === 'per_purchase'">
                    <div><label class="label" for="qty">Cantidad de sellos</label><input id="qty" type="number" min="1" :max="card.max" x-model.number="form.quantity" class="input"></div>
                </template>
                <template x-if="card.rule_type === 'per_amount'">
                    <div><label class="label" for="amt">Monto de la compra</label><input id="amt" type="number" min="0" step="0.01" x-model="form.amount" class="input"><p class="help">Los sellos se calculan en el servidor.</p></div>
                </template>
                <template x-if="card.rule_type === 'per_service' || card.rule_type === 'promotional'">
                    <div><label class="label" for="rule">Servicio / promoción</label><select id="rule" x-model="form.rule_id" class="input"><template x-for="r in card.rules" :key="r.id"><option :value="r.id" x-text="r.name + ' (' + r.stamps + ')'"></option></template></select></div>
                </template>
                <div><label class="label" for="ref">Referencia (opcional)</label><input id="ref" x-model="form.reference" maxlength="100" class="input"></div>
                <button type="button" class="btn-primary w-full" :disabled="busy" @click="award()"><span x-show="!busy">Asignar sellos</span><span x-show="busy" x-cloak>Registrando…</span></button>
            </div>
            <p x-show="state === 'found' && (!card.open || card.status !== 'active')" class="text-sm text-red-600">Esta tarjeta o promoción no está activa.</p>

            <button type="button" class="btn-secondary w-full" @click="reset()">Escanear otra tarjeta</button>
        </div>
    </template>
</div>
@endsection
