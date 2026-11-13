@extends('layouts.app')
@section('title', 'Historial')
@section('content')
<x-page-header title="Historial" subtitle="Registro completo e inmutable de movimientos de sellos" />
<form method="GET" class="card mb-4 grid gap-3 md:grid-cols-5">
    <div><label class="label" for="from">Desde</label><input id="from" type="date" name="from" value="{{ $from }}" class="input"></div>
    <div><label class="label" for="to">Hasta</label><input id="to" type="date" name="to" value="{{ $to }}" class="input"></div>
    <div><label class="label" for="type">Tipo</label><select id="type" name="type" class="input"><option value="">Todos</option>@foreach (trans('fidelidad.tx') as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label class="label" for="user">Empleado</label><select id="user" name="user" class="input"><option value="">Todos</option>@foreach ($staff as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
    <div class="flex items-end"><button class="btn-primary w-full">Filtrar</button></div>
</form>
<div class="card overflow-x-auto">
    @if ($transactions->isEmpty())<x-empty text="No hay movimientos en este período." />
    @else
    <table class="w-full"><thead><tr><th class="th">Fecha</th><th class="th">Cliente</th><th class="th">Tipo</th><th class="th">Sellos</th><th class="th">Saldo</th><th class="th">Empleado</th><th class="th">Sucursal</th><th class="th">Motivo</th></tr></thead>
    <tbody>@foreach ($transactions as $t)
        <tr class="border-t border-neutral-100 dark:border-neutral-800"><td class="td">{{ $t->created_at->format('d/m/Y H:i') }}</td><td class="td">{{ $t->customer?->name }}</td><td class="td">{{ __('fidelidad.tx.'.$t->type) }}</td><td class="td font-semibold">{{ $t->stamps > 0 ? '+' : '' }}{{ $t->stamps }}</td><td class="td">{{ $t->balance_after }}</td><td class="td">{{ $t->employee?->name ?? 'Sistema' }}</td><td class="td">{{ $t->branch?->name ?? '—' }}</td><td class="td">{{ $t->reason }}</td></tr>
    @endforeach</tbody></table>
    <div class="mt-4">{{ $transactions->links() }}</div>
    @endif
</div>
<h2 class="mb-3 mt-8 text-lg font-semibold">Canjes recientes</h2>
<div class="card">
    @forelse ($redemptions as $r)
        <div class="flex justify-between border-b border-neutral-100 py-1.5 text-sm dark:border-neutral-800"><span>{{ $r->entitlement?->reward?->name }} — {{ $r->entitlement?->card?->customer?->name }}</span><span class="text-neutral-500">{{ $r->redeemed_at->format('d/m/Y H:i') }} · {{ $r->employee?->name }}</span></div>
    @empty <x-empty text="Aún no hay canjes." /> @endforelse
</div>
@endsection
