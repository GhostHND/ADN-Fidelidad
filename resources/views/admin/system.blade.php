@extends('layouts.app')
@section('title', 'Sistema')
@section('content')
<x-page-header title="Estado del sistema" subtitle="Diagnóstico de configuración (sin mostrar secretos)" />
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card"><dl class="space-y-1 text-sm">@foreach ($checks as $k => $v)<div class="flex justify-between gap-3"><dt class="text-neutral-500">{{ $k }}</dt><dd class="font-medium">{{ $v }}</dd></div>@endforeach
        <div class="flex justify-between gap-3"><dt class="text-neutral-500">Trabajos Wallet pendientes</dt><dd class="font-medium">{{ $pendingJobs }}</dd></div></dl></div>
    <div class="card space-y-2 text-sm"><h2 class="font-semibold">Wallets</h2>
        @foreach ($providers as $name => $p)<div><b>{{ $name === 'apple' ? 'Apple Wallet' : 'Google Wallet' }}</b>: @if ($p['ok'])<span class="text-emerald-700">configurado (la verificación real requiere un pase instalado en un dispositivo)</span>@else<span class="text-amber-700">falta: {{ implode(', ', $p['missing']) }}</span>@endif</div>@endforeach</div>
</div>
@endsection
