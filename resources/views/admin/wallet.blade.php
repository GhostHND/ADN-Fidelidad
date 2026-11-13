@extends('layouts.app')
@section('title', 'Wallet')
@section('content')
<x-page-header title="Sincronización Wallet" subtitle="Cola de actualizaciones a Apple y Google">
    <form method="POST" action="{{ route('admin.wallet.process') }}">@csrf<button class="btn-primary">Procesar pendientes ahora</button></form>
</x-page-header>
<div class="mb-4 grid gap-3 md:grid-cols-2">
@foreach ($providers as $name => $p)
    <div class="card text-sm"><b>{{ $name === 'apple' ? 'Apple Wallet' : 'Google Wallet' }}</b>:
        @if ($p['ok'])<span class="text-emerald-700">configurado</span>@else<span class="text-amber-700">sin configurar — falta: {{ implode(', ', $p['missing']) }}</span>@endif</div>
@endforeach
</div>
<form method="GET" class="mb-3"><select name="status" class="input max-w-xs" onchange="this.form.submit()" aria-label="Estado"><option value="">Todos</option>@foreach (['pending','done','failed','skipped'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select></form>
<div class="card overflow-x-auto">
@if ($jobs->isEmpty())<x-empty text="No hay trabajos." />@else
<table class="w-full"><thead><tr><th class="th">#</th><th class="th">Plataforma</th><th class="th">Ámbito</th><th class="th">Motivo</th><th class="th">Estado</th><th class="th">Intentos</th><th class="th">Error</th><th class="th"></th></tr></thead><tbody>
@foreach ($jobs as $j)<tr class="border-t border-neutral-100 dark:border-neutral-800"><td class="td">{{ $j->id }}</td><td class="td">{{ $j->platform }}</td><td class="td">{{ $j->scope }}</td><td class="td">{{ $j->reason }}</td><td class="td">{{ $j->status }}</td><td class="td">{{ $j->attempts }}</td><td class="td max-w-xs truncate" title="{{ $j->last_error }}">{{ $j->last_error }}</td>
<td class="td">@if (in_array($j->status, ['failed','skipped']))<form method="POST" action="{{ route('admin.wallet.retry', $j) }}">@csrf<button class="btn-secondary !min-h-8">Reintentar</button></form>@endif</td></tr>@endforeach</tbody></table>
<div class="mt-4">{{ $jobs->links() }}</div>@endif
</div>
@endsection
