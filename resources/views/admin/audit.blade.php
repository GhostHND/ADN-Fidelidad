@extends('layouts.app')
@section('title', 'Auditoría')
@section('content')
<x-page-header title="Auditoría" subtitle="Acciones sensibles de la plataforma" />
<form method="GET" class="mb-3 flex gap-2"><input name="action" value="{{ request('action') }}" class="input max-w-xs" placeholder="Acción (ej. admin.)" aria-label="Acción"><button class="btn-secondary">Filtrar</button></form>
<div class="card overflow-x-auto">
@if ($logs->isEmpty())<x-empty />@else
<table class="w-full"><thead><tr><th class="th">Fecha</th><th class="th">Usuario</th><th class="th">Acción</th><th class="th">Negocio</th><th class="th">IP</th><th class="th">Detalle</th></tr></thead><tbody>
@foreach ($logs as $l)<tr class="border-t border-neutral-100 dark:border-neutral-800"><td class="td">{{ $l->created_at->format('d/m/Y H:i') }}</td><td class="td">{{ $l->user?->name ?? '—' }}</td><td class="td">{{ $l->action }}</td><td class="td">{{ $l->business_id }}</td><td class="td">{{ $l->ip }}</td><td class="td max-w-xs truncate" title="{{ json_encode($l->meta, JSON_UNESCAPED_UNICODE) }}">{{ json_encode($l->meta, JSON_UNESCAPED_UNICODE) }}</td></tr>@endforeach</tbody></table>
<div class="mt-4">{{ $logs->links() }}</div>@endif
</div>
@endsection
