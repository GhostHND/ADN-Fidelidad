@extends('layouts.app')
@section('title', 'Negocios')
@section('content')
<x-page-header title="Negocios" subtitle="Clientes de ADN Fidelidad">
    <a href="{{ route('admin.businesses.create') }}" class="btn-primary">Nuevo negocio</a>
</x-page-header>
<form method="GET" class="mb-4 flex gap-2"><input name="q" value="{{ request('q') }}" class="input max-w-sm" placeholder="Buscar por nombre o ID de Master" aria-label="Buscar"><button class="btn-secondary">Buscar</button></form>
<div class="card overflow-x-auto">
@if ($businesses->isEmpty())<x-empty text="No hay negocios." />@else
<table class="w-full"><thead><tr><th class="th">Negocio</th><th class="th">ID Master</th><th class="th">Estado</th><th class="th">Suscripción</th><th class="th">Plan</th><th class="th">Usuarios</th><th class="th"></th></tr></thead><tbody>
@foreach ($businesses as $b)
<tr class="border-t border-neutral-100 dark:border-neutral-800"><td class="td font-medium">{{ $b->name }}</td><td class="td">{{ $b->master_business_id ?? '—' }}</td><td class="td">{{ $b->status === 'active' ? 'Activo' : 'Suspendido' }}</td><td class="td">{{ __('fidelidad.mode.'.$subs->mode($b)) }}</td><td class="td">{{ $b->entitlement?->plan ?? '—' }}</td><td class="td">{{ $b->users_count }}</td><td class="td"><a class="underline" href="{{ route('admin.businesses.show', $b) }}">Ver</a></td></tr>
@endforeach</tbody></table>
<div class="mt-4">{{ $businesses->links() }}</div>
@endif
</div>
@endsection
