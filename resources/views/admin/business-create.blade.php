@extends('layouts.app')
@section('title', 'Nuevo negocio')
@section('content')
<x-page-header title="Nuevo negocio" subtitle="Alta manual (cuando Software Master aún no envía la suscripción)" />
<form method="POST" action="{{ route('admin.businesses.store') }}" class="card max-w-2xl space-y-4" x-data="guardedForm" @submit="submit">
    @csrf
    <x-field name="name" label="Nombre del negocio" required />
    <x-field name="master_business_id" label="ID de negocio en Software Master" help="Opcional. Es la llave que Master usará en sus eventos." />
    <hr class="border-neutral-200 dark:border-neutral-700">
    <x-field name="owner_name" label="Nombre del propietario" required />
    <x-field name="owner_email" type="email" label="Correo del propietario" required />
    <x-field name="owner_password" type="password" label="Contraseña inicial (mínimo 10 caracteres)" required minlength="10" />
    <hr class="border-neutral-200 dark:border-neutral-700">
    <div class="grid gap-4 md:grid-cols-3">
        <x-field name="plan" label="Plan" value="manual" />
        <div><label class="label" for="status">Estado</label><select id="status" name="status" class="input">@foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', 'active') === $s)>{{ __('fidelidad.status.'.$s) }}</option>@endforeach</select></div>
        <x-field name="ends_at" type="date" label="Vence" />
    </div>
    <fieldset><legend class="label">Funcionalidades</legend>
        <div class="grid gap-2 md:grid-cols-3">@foreach ($features as $f)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="features[]" value="{{ $f }}" @checked(in_array($f, old('features', [])))>{{ __('fidelidad.features.'.$f) }}</label>@endforeach</div></fieldset>
    <x-submit label="Crear negocio" loading="Creando…" />
</form>
@endsection
