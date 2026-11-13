@extends('layouts.app')
@section('title', 'Clientes')
@section('content')
<x-page-header title="Clientes" subtitle="Tarjetas emitidas y su actividad">
    @perm('customers.export')<a href="{{ route('app.customers.export') }}" class="btn-secondary">Exportar CSV</a>@endperm
</x-page-header>
<form method="GET" class="card mb-4 grid gap-3 md:grid-cols-5">
    <input name="q" value="{{ $q }}" class="input md:col-span-2" placeholder="Buscar por nombre, correo o teléfono" aria-label="Buscar">
    <select name="program" class="input" aria-label="Programa"><option value="">Todos los programas</option>@foreach ($programs as $p)<option value="{{ $p->id }}" @selected(request('program') == $p->id)>{{ $p->name }}</option>@endforeach</select>
    <select name="filter" class="input" aria-label="Filtro"><option value="">Todos</option><option value="inactive" @selected(request('filter') === 'inactive')>Inactivos 30 días</option><option value="reward" @selected(request('filter') === 'reward')>Con recompensa disponible</option></select>
    <button class="btn-primary">Buscar</button>
</form>
<div class="card overflow-x-auto">
    @if ($cards->isEmpty())
        <x-empty text="No se encontraron clientes. Comparte tu página pública para que se registren." />
    @else
    <table class="w-full"><thead><tr><th class="th">Cliente</th><th class="th">Contacto</th><th class="th">Programa</th><th class="th">Sellos</th><th class="th">Última actividad</th><th></th></tr></thead>
    <tbody>@foreach ($cards as $c)
        <tr class="border-t border-neutral-100 dark:border-neutral-800">
            <td class="td font-medium">{{ $c->customer->name }}</td>
            <td class="td">{{ $c->customer->email ?: $c->customer->phone }}</td>
            <td class="td">{{ $c->program->name }}</td>
            <td class="td">{{ $c->stamps_balance }} / {{ $c->program->stamps_goal }}</td>
            <td class="td">{{ $c->last_activity_at?->diffForHumans() ?? '—' }}</td>
            <td class="td"><a href="{{ route('app.customers.show', $c) }}" class="text-brand-600 underline">Ver</a></td>
        </tr>
    @endforeach</tbody></table>
    <div class="mt-4">{{ $cards->links() }}</div>
    @endif
</div>
@endsection
