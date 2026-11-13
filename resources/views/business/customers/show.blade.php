@extends('layouts.app')
@section('title', $card->customer->name)
@section('content')
<x-page-header :title="$card->customer->name" :subtitle="$card->program->name.' · '.$card->stamps_balance.' de '.$card->program->stamps_goal.' sellos · ciclo '.$card->cycle_number">
    <a href="{{ route('app.customers') }}" class="btn-secondary">Volver</a>
</x-page-header>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="card space-y-1 text-sm">
        <h2 class="mb-2 font-semibold">Datos</h2>
        <div>Correo: {{ $card->customer->email ?: '—' }}</div>
        <div>Teléfono: {{ $card->customer->phone ?: '—' }}</div>
        <div>Sellos históricos: {{ $card->lifetime_stamps }}</div>
        <div>Estado de tarjeta: {{ __('fidelidad.status.'.$card->status) }}</div>
        <div>Tarjeta web: <a class="text-brand-600 underline" href="{{ route('public.card', $card->uuid) }}" target="_blank" rel="noopener">abrir</a></div>
        @perm('stamps.reverse')
        @if ($subscriptionMode === 'full')
        <form method="POST" action="{{ route('app.customers.adjust', $card) }}" x-data="guardedForm" @submit="submit" class="mt-4 space-y-2 border-t border-neutral-100 pt-4 dark:border-neutral-800">
            @csrf <h3 class="font-semibold">Corrección administrativa</h3>
            <input type="number" name="stamps" min="-50" max="50" class="input" placeholder="Sellos (+/-)" aria-label="Sellos" required>
            <input name="reason" class="input" placeholder="Motivo (obligatorio)" aria-label="Motivo" maxlength="200" required>
            <x-submit label="Aplicar corrección" />
        </form>
        @endif
        @endperm
    </div>

    <div class="space-y-6 lg:col-span-2">
        <div class="card">
            <h2 class="mb-2 font-semibold">Recompensas</h2>
            @forelse ($entitlements as $e)
                <div class="flex justify-between border-b border-neutral-100 py-1.5 text-sm dark:border-neutral-800"><span>{{ $e->reward->name }} (ciclo {{ $e->cycle_number }})</span><span class="badge bg-brand-50 text-brand-600">{{ __('fidelidad.status.'.$e->status) }}</span></div>
            @empty <x-empty text="Todavía sin recompensas ganadas." /> @endforelse
        </div>
        <div class="card overflow-x-auto">
            <h2 class="mb-2 font-semibold">Movimientos</h2>
            <table class="w-full"><thead><tr><th class="th">Fecha</th><th class="th">Tipo</th><th class="th">Sellos</th><th class="th">Saldo</th><th class="th">Por</th><th class="th">Motivo</th><th></th></tr></thead>
            <tbody>@foreach ($transactions as $t)
                <tr class="border-t border-neutral-100 dark:border-neutral-800">
                    <td class="td">{{ $t->created_at->format('d/m/Y H:i') }}</td><td class="td">{{ __('fidelidad.tx.'.$t->type) }}</td>
                    <td class="td font-semibold">{{ $t->stamps > 0 ? '+' : '' }}{{ $t->stamps }}</td><td class="td">{{ $t->balance_after }}</td>
                    <td class="td">{{ $t->employee?->name ?? 'Sistema' }}</td><td class="td">{{ $t->reason }}</td>
                    <td class="td">
                        @if ($t->type === 'earn' && ! in_array($t->id, $reversed, true) && $t->cycle_number === $card->cycle_number && $subscriptionMode === 'full')
                            @perm('stamps.reverse')
                            <div x-data="{ o: false }">
                                <button type="button" class="text-xs text-red-600 underline" @click="o = !o">Revertir</button>
                                <form x-show="o" x-cloak method="POST" action="{{ route('app.transactions.reverse', $t) }}" x-data="guardedForm" @submit="submit" class="mt-1 flex gap-1">
                                    @csrf<input name="reason" class="input !min-h-8" placeholder="Motivo" aria-label="Motivo" required maxlength="200"><x-submit label="Confirmar" class="btn-danger !min-h-8" />
                                </form>
                            </div>
                            @endperm
                        @endif
                    </td>
                </tr>
            @endforeach</tbody></table>
        </div>
    </div>
</div>
@endsection
