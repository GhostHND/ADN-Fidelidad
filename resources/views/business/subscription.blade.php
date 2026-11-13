@extends('layouts.app')
@section('title', 'Suscripción')
@section('content')
<x-page-header title="Suscripción" subtitle="Estado sincronizado desde ADN Publicidad Software Master" />
<div class="card max-w-2xl space-y-3">
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <dt class="font-semibold">Estado operativo</dt><dd>{{ __('fidelidad.mode.'.$summary['mode']) }}</dd>
        <dt class="font-semibold">Plan</dt><dd>{{ $summary['plan'] ?? '—' }}</dd>
        <dt class="font-semibold">Estado de la suscripción</dt><dd>{{ __('fidelidad.status.'.$summary['status']) }}</dd>
        <dt class="font-semibold">Vigencia hasta</dt><dd>{{ $summary['ends_at']?->format('d/m/Y H:i') ?? 'Sin fecha de fin' }}</dd>
        <dt class="font-semibold">Última sincronización</dt><dd>{{ $summary['last_synced_at']?->diffForHumans() ?? 'Nunca' }}</dd>
        <dt class="font-semibold">Funciones incluidas</dt>
        <dd>@forelse ($summary['features'] as $f)<span class="badge bg-brand-50 text-brand-600 mr-1">{{ __('fidelidad.feature.'.$f) }}</span>@empty — @endforelse</dd>
    </dl>
    <p class="text-sm text-neutral-500">Para renovar o cambiar de plan, contacta a ADN Publicidad. Tus datos se conservan siempre.</p>
</div>
@endsection
