@extends('layouts.app')
@section('title', 'Tarjetas de fidelidad')
@section('content')
<x-page-header title="Tarjetas de fidelidad" subtitle="Programas de sellos con recompensas">
    @if ($subscriptionMode === 'full')<a href="{{ route('app.programs.create') }}" class="btn-primary">Nueva tarjeta</a>@endif
</x-page-header>
@if ($programs->isEmpty())
    <x-empty text="Aún no tienes tarjetas. Crea la primera para empezar a registrar clientes." />
@else
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach ($programs as $p)
        <div class="card">
            <div class="mb-2 h-16 rounded-lg p-3 text-sm font-bold" style="background: {{ $p->design['background_color'] ?? '#1F2937' }}; color: {{ $p->design['foreground_color'] ?? '#fff' }}">{{ $p->design['card_title'] ?? $p->name }}</div>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">{{ $p->name }}</h2>
                <span class="badge bg-brand-50 text-brand-600">{{ __('fidelidad.status.'.$p->status) }}</span>
            </div>
            <p class="mt-1 text-sm text-neutral-500">Meta: {{ $p->stamps_goal }} sellos · {{ $p->cards_count }} tarjetas · {{ $p->rewards_count }} recompensas · v{{ $p->current_version }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('app.programs.edit', $p) }}" class="btn-secondary !min-h-8">Editar</a>
                @if ($subscriptionMode === 'full')
                <form method="POST" action="{{ route('app.programs.duplicate', $p) }}" x-data="guardedForm" @submit="submit">@csrf<x-submit label="Duplicar" loading="Duplicando…" class="btn-secondary !min-h-8" /></form>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection
