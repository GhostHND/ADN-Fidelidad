@extends('layouts.app')
@section('title', 'Panel ADN')
@section('content')
<x-page-header title="Panel de administración ADN" subtitle="Vista global de la plataforma">
    <a href="{{ route('admin.businesses.create') }}" class="btn-primary">Nuevo negocio</a>
</x-page-header>
<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    <x-stat label="Negocios" :value="$total" />
    <x-stat label="Tarjetas emitidas" :value="$cards" />
    <x-stat label="Sellos hoy" :value="$stampsToday" />
    <x-stat label="Trabajos Wallet fallidos" :value="$failedJobs" />
    <x-stat label="Eventos Master con error (7 d)" :value="$failedEvents" />
    @foreach (['full' => 'Negocios operativos', 'readonly' => 'Solo lectura', 'blocked' => 'Bloqueados'] as $k => $l)
        <x-stat :label="$l" :value="$byMode[$k] ?? 0" />
    @endforeach
</div>
@endsection
