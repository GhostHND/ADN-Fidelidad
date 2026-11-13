@extends('layouts.app')
@section('title', 'Mi negocio')
@section('content')
<x-page-header title="Mi negocio" subtitle="Datos públicos y de contacto. Se muestran en tu página de registro y en las tarjetas.">
    <a href="{{ $business->publicUrl() }}" target="_blank" rel="noopener" class="btn-secondary">Ver página pública</a>
</x-page-header>

<form method="POST" action="{{ route('app.business.update') }}" enctype="multipart/form-data" x-data="guardedForm" @submit="submit" class="card grid gap-4 md:grid-cols-2">
    @csrf @method('PUT')
    <x-field name="name" label="Nombre del negocio" :value="$business->name" required />
    <x-field name="email" label="Correo de contacto" type="email" :value="$business->email" />
    <x-field name="phone" label="Teléfono" :value="$business->phone" />
    <x-field name="whatsapp" label="WhatsApp" :value="$business->whatsapp" />
    <x-field name="address" label="Dirección" :value="$business->address" />
    <x-field name="website" label="Sitio web" type="url" :value="$business->website" />
    <x-field name="primary_color" label="Color principal" type="color" :value="$business->primary_color" required />
    <x-field name="secondary_color" label="Color secundario" type="color" :value="$business->secondary_color" required />
    <x-field name="description" label="Descripción" type="textarea" :value="$business->description" class="md:col-span-2" />
    <div class="md:col-span-2">
        <label class="label" for="logo">Logotipo (PNG, JPG o WebP, máx. 2 MB)</label>
        @if ($business->logoUrl())<img src="{{ $business->logoUrl() }}" alt="Logotipo actual" class="mb-2 h-16 w-auto rounded">@endif
        <input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="input">
        @error('logo')<p class="error">{{ $message }}</p>@enderror
    </div>
    <x-field name="privacy_notice" label="Aviso de privacidad (se muestra al registrarse)" type="textarea" :value="$business->privacy_notice" class="md:col-span-2" help="Escribe aquí el aviso legal de tu negocio. Si lo dejas vacío se usa un texto básico." />
    <x-field name="program_terms" label="Términos generales del programa" type="textarea" :value="$business->program_terms" class="md:col-span-2" />
    <div class="md:col-span-2"><x-submit label="Guardar cambios" /></div>
</form>

<h2 class="mb-3 mt-8 text-lg font-semibold">Sucursales</h2>
<div class="card">
    @forelse ($branches as $b)
        <form method="POST" action="{{ route('app.branches.update', $b) }}" x-data="guardedForm" @submit="submit" class="mb-3 grid gap-2 border-b border-neutral-100 pb-3 md:grid-cols-5 dark:border-neutral-800">
            @csrf @method('PUT')
            <input name="name" value="{{ $b->name }}" class="input" aria-label="Nombre" required>
            <input name="address" value="{{ $b->address }}" class="input" aria-label="Dirección" placeholder="Dirección">
            <input name="phone" value="{{ $b->phone }}" class="input" aria-label="Teléfono" placeholder="Teléfono">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($b->is_active)> Activa</label>
            <x-submit label="Guardar" class="btn-secondary" />
        </form>
    @empty
        <x-empty text="Aún no tienes sucursales. Es opcional: úsalas para saber dónde se otorga cada sello." />
    @endforelse
    <form method="POST" action="{{ route('app.branches.store') }}" x-data="guardedForm" @submit="submit" class="mt-4 grid gap-2 md:grid-cols-4">
        @csrf
        <input name="name" class="input" placeholder="Nueva sucursal" aria-label="Nombre de la nueva sucursal" required>
        <input name="address" class="input" placeholder="Dirección" aria-label="Dirección">
        <input name="phone" class="input" placeholder="Teléfono" aria-label="Teléfono">
        <x-submit label="Agregar sucursal" loading="Agregando…" />
    </form>
</div>
@endsection
