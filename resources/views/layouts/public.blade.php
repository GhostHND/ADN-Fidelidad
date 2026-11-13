<!DOCTYPE html>
<html lang="es" x-data="theme">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('fidelidad.brand.name'))</title>
    @section('manifest')
        <link rel="manifest" href="/manifest.webmanifest">
    @show
    <meta name="theme-color" content="#0FA7B4">
    @yield('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="mx-auto flex min-h-screen max-w-3xl flex-col px-4 py-8">
    @yield('content')
    <footer class="mt-auto pt-8 text-center text-xs text-neutral-500">{{ config('fidelidad.brand.name') }} · {{ config('fidelidad.brand.signature') }}</footer>
</main>
<x-flash />
</body>
</html>
