@extends('layouts.app')
@section('title', 'Empleados')
@section('content')
<x-page-header title="Empleados" subtitle="Roles y permisos de quienes operan el programa{{ $limit ? ' · límite del plan: '.$limit : '' }}" />

<div class="card overflow-x-auto">
    @foreach ($staff as $u)
        @php
            $p = $u->pivot;
            $custom = $p->permissions ? json_decode($p->permissions, true) : null;
        @endphp
        <details class="border-b border-neutral-100 py-3 dark:border-neutral-800">
            <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2">
                <span><strong>{{ $u->name }}</strong> <span class="text-sm text-neutral-500">{{ $u->email }}</span></span>
                <span><span class="badge bg-brand-50 text-brand-600">{{ __('fidelidad.role.'.$p->role) }}</span>
                    @unless ($p->is_active)<span class="badge bg-neutral-200 text-neutral-700">Inactivo</span>@endunless</span>
            </summary>
            <form method="POST" action="{{ route('app.staff.update', $u) }}" x-data="guardedForm" @submit="submit" class="mt-3 grid gap-3 md:grid-cols-3">
                @csrf @method('PUT')
                <div><label class="label">Rol</label>
                    <select name="role" class="input">@foreach (config('fidelidad.roles') as $role => $_)<option value="{{ $role }}" @selected($p->role === $role)>{{ __('fidelidad.role.'.$role) }}</option>@endforeach</select></div>
                <div><label class="label">Sucursal</label>
                    <select name="branch_id" class="input"><option value="">Todas</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected($p->branch_id == $b->id)>{{ $b->name }}</option>@endforeach</select></div>
                <div><label class="label">Nueva contraseña (opcional)</label><input type="password" name="password" class="input" autocomplete="new-password"></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($p->is_active)> Acceso activo</label>
                <div class="md:col-span-3" x-data="{ custom: {{ $custom !== null ? 'true' : 'false' }} }">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="custom_permissions" value="1" x-model="custom"> Personalizar permisos (si no, se usan los del rol)</label>
                    <div x-show="custom" x-cloak class="mt-2 grid gap-1 sm:grid-cols-2">
                        @foreach (config('fidelidad.permissions') as $key => $text)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $custom ?? config('fidelidad.roles.'.$p->role, []), true) || in_array('*', $custom ?? config('fidelidad.roles.'.$p->role, []), true))> {{ $text }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="md:col-span-3"><x-submit label="Guardar empleado" /></div>
            </form>
        </details>
    @endforeach
</div>

<h2 class="mb-3 mt-8 text-lg font-semibold">Agregar empleado</h2>
@if ($canAdd)
<form method="POST" action="{{ route('app.staff.store') }}" x-data="guardedForm" @submit="submit" class="card grid gap-3 md:grid-cols-3">
    @csrf
    <x-field name="name" label="Nombre" required />
    <x-field name="email" label="Correo" type="email" required />
    <x-field name="password" label="Contraseña inicial" type="password" required help="Mínimo 10 caracteres. Compártela de forma segura." />
    <div><label class="label">Rol</label><select name="role" class="input">@foreach (config('fidelidad.roles') as $role => $_)<option value="{{ $role }}" @selected($role === 'cashier')>{{ __('fidelidad.role.'.$role) }}</option>@endforeach</select></div>
    <div><label class="label">Sucursal</label><select name="branch_id" class="input"><option value="">Todas</option>@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
    <div class="flex items-end"><x-submit label="Agregar" loading="Agregando…" /></div>
</form>
@else
<div class="card text-sm">Tu plan alcanzó el límite de empleados. Contacta a ADN Publicidad para ampliarlo.</div>
@endif
@endsection
