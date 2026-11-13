@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<x-page-header title="Dashboard" subtitle="Resumen de actividad del programa de fidelidad">
    <form method="GET" class="flex flex-wrap items-end gap-2">
        <div><label class="label" for="from">Desde</label><input id="from" type="date" name="from" value="{{ $from }}" class="input"></div>
        <div><label class="label" for="to">Hasta</label><input id="to" type="date" name="to" value="{{ $to }}" class="input"></div>
        <button class="btn-primary">Filtrar</button>
    </form>
</x-page-header>

<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    @foreach ($kpis as $label => $value)
        <x-stat :label="$label" :value="$value" />
    @endforeach
</div>

<div class="card mt-6">
    <h2 class="mb-3 font-semibold">Actividad diaria</h2>
    <div class="h-64" x-data="statsChart(@js($series))"><canvas x-ref="canvas" aria-label="Gráfica de sellos y clientes nuevos por día" role="img"></canvas></div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="card lg:col-span-1">
        <h2 class="mb-3 font-semibold">Empleados con más sellos</h2>
        @forelse ($employees as $e)
            <div class="flex justify-between border-b border-neutral-100 py-1.5 text-sm dark:border-neutral-800"><span>{{ $e->name }}</span><span class="font-semibold">{{ $e->stamps }} <span class="text-neutral-500">({{ $e->ops }} op.)</span></span></div>
        @empty <x-empty text="Sin operaciones en el período." /> @endforelse
        <h2 class="mb-3 mt-6 font-semibold">Sucursales</h2>
        @forelse ($branches as $e)
            <div class="flex justify-between border-b border-neutral-100 py-1.5 text-sm dark:border-neutral-800"><span>{{ $e->name }}</span><span class="font-semibold">{{ $e->stamps }}</span></div>
        @empty <x-empty text="Sin datos por sucursal." /> @endforelse
    </div>
    <div class="card overflow-x-auto lg:col-span-2">
        <h2 class="mb-3 font-semibold">Últimos movimientos</h2>
        @if ($recent->isEmpty())
            <x-empty text="Todavía no hay movimientos. Escanea una tarjeta para comenzar." />
        @else
            <table class="w-full"><thead><tr><th class="th">Fecha</th><th class="th">Cliente</th><th class="th">Tipo</th><th class="th">Sellos</th></tr></thead>
            <tbody>@foreach ($recent as $t)
                <tr class="border-t border-neutral-100 dark:border-neutral-800"><td class="td">{{ $t->created_at->format('d/m H:i') }}</td><td class="td">{{ $t->customer?->name }}</td><td class="td">{{ __('fidelidad.tx.'.$t->type) }}</td><td class="td font-semibold">{{ $t->stamps > 0 ? '+' : '' }}{{ $t->stamps }}</td></tr>
            @endforeach</tbody></table>
        @endif
    </div>
</div>
@endsection
