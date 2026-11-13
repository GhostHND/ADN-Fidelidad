@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
<x-page-header title="Usuarios" subtitle="Todos los usuarios de la plataforma" />
<form method="GET" class="mb-4 flex gap-2"><input name="q" value="{{ request('q') }}" class="input max-w-sm" placeholder="Buscar" aria-label="Buscar"><button class="btn-secondary">Buscar</button></form>
<div class="card mb-6 overflow-x-auto">
<table class="w-full"><thead><tr><th class="th">Nombre</th><th class="th">Correo</th><th class="th">Tipo</th><th class="th">Negocios</th><th class="th">Estado</th><th class="th"></th></tr></thead><tbody>
@foreach ($users as $u)
<tr class="border-t border-neutral-100 dark:border-neutral-800"><td class="td">{{ $u->name }}</td><td class="td">{{ $u->email }}</td><td class="td">{{ $u->is_superadmin ? 'Superadmin' : 'Negocio' }}</td><td class="td">{{ $u->businesses->pluck('name')->join(', ') ?: '—' }}</td><td class="td">{{ $u->is_active ? 'Activo' : 'Inactivo' }}</td>
<td class="td"><form method="POST" action="{{ route('admin.users.update', $u) }}" class="flex gap-1">@csrf @method('PUT')<input type="hidden" name="is_active" value="{{ $u->is_active ? 0 : 1 }}"><button class="btn-secondary !min-h-8">{{ $u->is_active ? 'Desactivar' : 'Activar' }}</button></form></td></tr>
@endforeach</tbody></table>
<div class="mt-4">{{ $users->links() }}</div>
</div>
<form method="POST" action="{{ route('admin.users.store') }}" class="card max-w-xl space-y-4" x-data="guardedForm" @submit="submit">
    @csrf<h2 class="font-semibold">Nuevo superadministrador</h2>
    <x-field name="name" label="Nombre" required /><x-field name="email" type="email" label="Correo" required /><x-field name="password" type="password" label="Contraseña (mínimo 10)" required minlength="10" />
    <x-submit label="Crear" loading="Creando…" />
</form>
@endsection
