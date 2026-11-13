@extends('layouts.app')
@section('title', $business->name)
@section('content')
<x-page-header :title="$business->name" subtitle="Detalle del negocio">
    <form method="POST" action="{{ route('admin.businesses.pull', $business) }}">@csrf<button class="btn-secondary">Sincronizar con Master</button></form>
    <form method="POST" action="{{ route('admin.businesses.impersonate', $business) }}">@csrf<button class="btn-primary">Entrar como este negocio</button></form>
</x-page-header>
<div class="grid gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('admin.businesses.update', $business) }}" class="card space-y-4" x-data="guardedForm" @submit="submit">
        @csrf @method('PUT')
        <h2 class="font-semibold">Datos</h2>
        <div><label class="label" for="status">Estado del negocio</label><select id="status" name="status" class="input"><option value="active" @selected($business->status === 'active')>Activo</option><option value="suspended" @selected($business->status === 'suspended')>Suspendido</option></select></div>
        <x-field name="master_business_id" label="ID en Software Master" :value="$business->master_business_id" />
        <x-submit />
    </form>
    <div class="card"><h2 class="mb-2 font-semibold">Suscripción</h2>
        <dl class="space-y-1 text-sm">
            @foreach ($summary as $k => $v)<div class="flex justify-between gap-3"><dt class="text-neutral-500">{{ $k }}</dt><dd class="text-right font-medium">{{ is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v }}</dd></div>@endforeach
        </dl>
        @if ($business->entitlement?->subscription_id === 'manual')<p class="mt-3 text-xs text-amber-700">Autorización manual: será reemplazada por el primer evento de Software Master.</p>@endif
    </div>
    <div class="card"><h2 class="mb-2 font-semibold">Usuarios</h2>
        @foreach ($business->users as $u)<div class="flex justify-between py-1 text-sm"><span>{{ $u->name }} ({{ $u->email }})</span><span class="text-neutral-500">{{ __('fidelidad.role.'.$u->pivot->role) }}</span></div>@endforeach</div>
    <div class="card"><h2 class="mb-2 font-semibold">Últimos eventos de Master</h2>
        @forelse ($events as $e)<div class="flex justify-between py-1 text-sm"><span>{{ $e->type }} v{{ $e->event_version }}</span><span class="text-neutral-500">{{ $e->status }} · {{ $e->created_at->format('d/m H:i') }}</span></div>@empty<x-empty text="Sin eventos." />@endforelse</div>
</div>
@endsection
