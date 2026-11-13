@extends('layouts.public')
@section('title', $business->name.' · Tarjeta de fidelidad')
@section('content')
<div class="card text-center">
    @if ($business->logoUrl())<img src="{{ $business->logoUrl() }}" alt="Logo de {{ $business->name }}" class="mx-auto mb-3 h-20 w-20 rounded-xl object-contain">@endif
    <h1 class="text-2xl font-bold">{{ $business->name }}</h1>
    @if ($business->description)<p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">{{ $business->description }}</p>@endif
</div>

@if (! $available)
    <div class="card mt-4 text-center text-sm">Por ahora este negocio no tiene una tarjeta de fidelidad disponible para nuevos registros.</div>
@else
<div class="card mt-4">
    <h2 class="text-lg font-semibold">{{ $program->name }}</h2>
    @if ($program->description)<p class="mt-1 text-sm">{{ $program->description }}</p>@endif
    <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">Completa {{ $program->stamps_goal }} sellos y gana: {{ $program->rewards()->where('status', 'active')->pluck('name')->join(', ') ?: 'una recompensa' }}.</p>

    <form method="POST" action="{{ route('public.register', $business->slug) }}" class="mt-5 space-y-4" x-data="guardedForm" @submit="submit">
        @csrf
        <x-field name="name" label="Nombre completo" required maxlength="120" autocomplete="name" />
        <x-field name="email" type="email" label="Correo electrónico" autocomplete="email" help="Indica correo o teléfono (al menos uno)." />
        <x-field name="phone" type="tel" label="Teléfono" autocomplete="tel" />
        <x-field name="birthdate" type="date" label="Fecha de nacimiento (opcional)" />
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent_privacy" value="1" required class="mt-1" @checked(old('consent_privacy'))>
            <span>Acepto el tratamiento de mis datos para operar mi tarjeta de fidelidad. <a href="#privacidad" class="underline">Ver aviso</a>.</span></label>
        @error('consent_privacy')<p class="error">Debes aceptar el aviso de privacidad.</p>@enderror
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent_marketing" value="1" class="mt-1" @checked(old('consent_marketing'))><span>Quiero recibir promociones (opcional).</span></label>
        <x-submit label="Obtener mi tarjeta" loading="Creando tu tarjeta…" class="btn-primary w-full" />
    </form>
</div>
@endif

<div id="privacidad" class="mt-4 space-y-2 text-xs text-neutral-600 dark:text-neutral-400">
    <p><b>Aviso de privacidad.</b> {{ $business->privacy_notice ?: 'Usamos tu nombre y contacto únicamente para identificar tu tarjeta y registrar tus sellos y recompensas. Puedes pedir la eliminación de tus datos personales desde tu tarjeta en cualquier momento.' }}</p>
    @if ($program?->terms || $business->program_terms)<p><b>Términos del programa.</b> {{ $program?->terms ?: $business->program_terms }}</p>@endif
    @if ($business->address || $business->phone)<p>{{ $business->address }} {{ $business->phone }}</p>@endif
</div>
@endsection
