@extends('layouts.public')
@section('title', 'Mi tarjeta · '.$business->name)
@section('manifest')
@endsection
@section('content')
@php($design = $view->design())
<div class="overflow-hidden rounded-2xl shadow-lg" style="background: {{ $design['background_color'] }}; color: {{ $design['foreground_color'] }}">
    <div class="flex items-center gap-3 p-4">
        @if ($business->logoUrl())<img src="{{ $business->logoUrl() }}" alt="" class="h-12 w-12 rounded-lg bg-white/90 object-contain p-1">@endif
        <div><div class="text-lg font-bold">{{ $design['card_title'] ?? $business->name }}</div><div class="text-xs opacity-80">{{ $card->customer->name }}</div></div>
    </div>
    <img src="{{ $view->stripUrl() }}" alt="Progreso: {{ $view->progressText() }}" class="w-full" width="750" height="246">
    <div class="p-4 text-center">
        <div class="text-3xl font-extrabold" aria-live="polite">{{ $view->progressText() }}</div>
        <div class="mt-1 text-sm opacity-90">{{ $view->rewardSummary() }}</div>
    </div>
</div>

@if ($view->availableRewards()->isNotEmpty())
    <div class="card mt-4 border-brand text-center"><div class="font-semibold">¡Tienes recompensas listas!</div>
        @foreach ($view->availableRewards() as $e)<div class="text-sm">{{ $e->reward->name }}</div>@endforeach
        <div class="mt-1 text-xs text-neutral-500">Muestra tu QR en el negocio para canjearlas.</div></div>
@endif

@unless ($anonymized)
<div class="card mt-4 text-center">
    <img src="{{ url('/tarjeta/'.$card->uuid.'/qr.svg') }}" alt="Código QR de tu tarjeta" class="mx-auto h-56 w-56 rounded bg-white p-2">
    <p class="mt-2 text-xs text-neutral-500">Muestra este código al pagar para recibir tus sellos.</p>
</div>
@endunless

@unless ($anonymized)
<div class="card mt-4 space-y-2">
    <div class="font-semibold">Guardar en tu teléfono</div>
    @if ($apple)<a class="btn-primary w-full" href="{{ url('/tarjeta/'.$card->uuid.'/apple.pkpass') }}">Añadir a Apple Wallet</a>@endif
    @if ($google)<a class="btn-primary w-full" href="{{ url('/tarjeta/'.$card->uuid.'/google') }}">Añadir a Google Wallet</a>@endif
    @if ($appleInDevelopment)
        <p class="rounded-lg bg-neutral-100 p-3 text-sm text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">Apple Wallet está en desarrollo.</p>
    @endif
    <details class="rounded-lg border border-neutral-200 p-3 text-sm dark:border-neutral-700">
        <summary class="cursor-pointer font-semibold">Guardar esta tarjeta en iPhone</summary>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Abre esta tarjeta en Safari.</li>
            <li>Presiona el botón Compartir y elige «Agregar a pantalla de inicio».</li>
            <li>Presiona «Agregar» para guardar el acceso directo.</li>
        </ol>
        <p class="mt-2">No es un pase de Apple Wallet. Abre la tarjeta con conexión a internet y actualiza la página para ver los sellos y recompensas más recientes. No envía notificaciones automáticas.</p>
        <p class="mt-2">Este enlace da acceso a tu tarjeta y QR; no lo compartas.</p>
    </details>
</div>
@endunless

<details class="card mt-4 text-sm"><summary class="cursor-pointer font-medium">Privacidad y mis datos</summary>
    <p class="mt-2">Puedes eliminar tus datos personales. Tu tarjeta dejará de identificarte; el historial de sellos se conserva sin tu nombre.</p>
    @unless ($anonymized)
    <form method="POST" action="{{ route('public.card.privacy', $card->uuid) }}" class="mt-3 space-y-2" x-data="guardedForm" @submit="submit">
        @csrf
        <label class="flex items-start gap-2"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>Entiendo que esta acción no se puede deshacer.</span></label>
        <x-submit label="Eliminar mis datos personales" loading="Procesando…" class="btn-danger" />
    </form>
    @endunless
</details>
@endsection
