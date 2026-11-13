@extends('layouts.app')
@section('title', 'Reportes')
@section('content')
<x-page-header title="Reportes" subtitle="Indicadores por período">
    <form method="GET" class="flex flex-wrap items-end gap-2">
        <div><label class="label" for="from">Desde</label><input id="from" type="date" name="from" value="{{ $from }}" class="input"></div>
        <div><label class="label" for="to">Hasta</label><input id="to" type="date" name="to" value="{{ $to }}" class="input"></div>
        <button class="btn-primary">Filtrar</button>
    </form>
</x-page-header>
@if ($locked)
    <div class="card text-sm">Tu plan actual no incluye reportes avanzados. Contacta a ADN Publicidad para habilitarlos.</div>
@else
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">@foreach ($kpis as $label => $value)<x-stat :label="$label" :value="$value" />@endforeach</div>
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card"><h2 class="mb-2 font-semibold">Empleados</h2>@forelse ($employees as $e)<div class="flex justify-between py-1 text-sm"><span>{{ $e->name }}</span><b>{{ $e->stamps }}</b></div>@empty<x-empty />@endforelse</div>
        <div class="card"><h2 class="mb-2 font-semibold">Sucursales</h2>@forelse ($branches as $e)<div class="flex justify-between py-1 text-sm"><span>{{ $e->name }}</span><b>{{ $e->stamps }}</b></div>@empty<x-empty />@endforelse</div>
        <div class="card"><h2 class="mb-2 font-semibold">Clientes más frecuentes</h2>@forelse ($top as $c)<div class="flex justify-between py-1 text-sm"><span>{{ $c->customer->name }}</span><b>{{ $c->lifetime_stamps }}</b></div>@empty<x-empty />@endforelse</div>
    </div>
@endif
@endsection
