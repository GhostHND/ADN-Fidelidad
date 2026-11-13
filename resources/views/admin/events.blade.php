@extends('layouts.app')
@section('title', 'Integración Master')
@section('content')
<x-page-header title="Integración con Software Master" subtitle="Eventos recibidos de suscripciones" />
<div class="card overflow-x-auto">
@if ($events->isEmpty())<x-empty text="Aún no se han recibido eventos." />@else
<table class="w-full"><thead><tr><th class="th">Fecha</th><th class="th">Evento</th><th class="th">Tipo</th><th class="th">Negocio Master</th><th class="th">Versión</th><th class="th">Estado</th><th class="th">Error</th></tr></thead><tbody>
@foreach ($events as $e)<tr class="border-t border-neutral-100 dark:border-neutral-800"><td class="td">{{ $e->created_at->format('d/m/Y H:i') }}</td><td class="td">{{ $e->event_id }}</td><td class="td">{{ $e->type }}</td><td class="td">{{ $e->master_business_id }}</td><td class="td">{{ $e->event_version }}</td><td class="td">{{ $e->status }}</td><td class="td">{{ $e->error }}</td></tr>@endforeach</tbody></table>
<div class="mt-4">{{ $events->links() }}</div>@endif
</div>
@endsection
