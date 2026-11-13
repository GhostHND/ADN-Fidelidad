@extends('layouts.public')
@section('title', 'Iniciar sesión')
@section('content')
<div class="my-auto mx-auto w-full max-w-sm">
    <div class="mb-6 flex justify-center"><x-brand /></div>
    <form method="POST" action="{{ route('login') }}" x-data="guardedForm" @submit="submit" class="card space-y-4">
        @csrf
        <h1 class="text-xl font-bold">Iniciar sesión</h1>
        <x-field name="email" label="Correo electrónico" type="email" required autocomplete="username" autofocus />
        <x-field name="password" label="Contraseña" type="password" required autocomplete="current-password" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Mantener sesión iniciada</label>
        <x-submit label="Entrar" loading="Entrando…" class="btn-primary w-full" />
    </form>
</div>
@endsection
