<!DOCTYPE html>
<html lang="es" x-data="theme">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Panel') · {{ config('fidelidad.brand.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php
    $isAdmin = request()->is('admin*');
    $nav = $isAdmin ? [
        ['admin.dashboard', 'Resumen', 'admin'],
        ['admin.businesses', 'Negocios', 'admin/negocios*'],
        ['admin.users', 'Usuarios', 'admin/usuarios*'],
        ['admin.wallet', 'Wallet', 'admin/wallet*'],
        ['admin.events', 'Integración Master', 'admin/integracion*'],
        ['admin.audit', 'Auditoría', 'admin/auditoria*'],
        ['admin.system', 'Sistema', 'admin/sistema*'],
    ] : [
        ['app.dashboard', 'Dashboard', 'app', 'dashboard.view'],
        ['app.scan', 'Escanear', 'app/escanear*', 'stamps.assign'],
        ['app.programs', 'Tarjetas', 'app/tarjetas*', 'programs.manage'],
        ['app.customers', 'Clientes', 'app/clientes*', 'customers.view'],
        ['app.history', 'Historial', 'app/historial*', 'history.view'],
        ['app.reports', 'Reportes', 'app/reportes*', 'dashboard.view'],
        ['app.staff', 'Empleados', 'app/empleados*', 'staff.manage'],
        ['app.business', 'Mi negocio', 'app/mi-negocio*', 'business.manage'],
        ['app.subscription', 'Suscripción', 'app/suscripcion*', null],
    ];
@endphp
<div x-data="{ menu: false }" class="min-h-screen lg:flex">
    <aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full bg-charcoal p-4 text-white transition lg:static lg:translate-x-0" :class="{ 'translate-x-0': menu }">
        <div class="mb-6"><x-brand /></div>
        <nav class="space-y-1" aria-label="Navegación principal">
            @foreach ($nav as $item)
                @if ($isAdmin || ! $item[3] || (auth()->user()->is_superadmin && session()->has('impersonate_business_id')) || auth()->user()->hasPermissionIn($currentBusiness, $item[3]))
                    <a href="{{ route($item[0]) }}" class="nav-link {{ request()->is($item[2]) ? 'nav-link-active' : '' }}">{{ $item[1] }}</a>
                @endif
            @endforeach
        </nav>
    </aside>
    <div x-show="menu" x-cloak class="fixed inset-0 z-30 bg-black/50 lg:hidden" @click="menu = false"></div>

    <div class="min-w-0 flex-1">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 bg-white px-4 py-3 dark:border-neutral-800 dark:bg-charcoal">
            <div class="flex items-center gap-3">
                <button type="button" class="btn-secondary lg:hidden" @click="menu = !menu" aria-label="Abrir menú">☰</button>
                @if (! $isAdmin && isset($currentBusiness))
                    <form method="POST" action="{{ route('app.switch') }}" class="flex items-center gap-2">
                        @csrf
                        @if (($memberships ?? collect())->count() > 1)
                            <select name="business_id" class="input !w-auto" onchange="this.form.submit()" aria-label="Negocio activo">
                                @foreach ($memberships as $m)<option value="{{ $m->id }}" @selected($m->id === $currentBusiness->id)>{{ $m->name }}</option>@endforeach
                            </select>
                        @else
                            <span class="font-semibold">{{ $currentBusiness->name }}</span>
                        @endif
                    </form>
                @else
                    <span class="font-semibold">Administración ADN</span>
                @endif
            </div>
            <div class="flex items-center gap-2 text-sm">
                <x-first-use-guide />
                @if (! $isAdmin && auth()->user()->is_superadmin && session()->has('impersonate_business_id'))
                    <form method="POST" action="{{ route('admin.leave') }}">@csrf<button class="btn-secondary !min-h-8">Salir del negocio</button></form>
                @endif
                @if (auth()->user()->is_superadmin && ! session()->has('impersonate_business_id') && ! $isAdmin)
                    <a class="btn-secondary !min-h-8" href="{{ route('admin.dashboard') }}">Admin</a>
                @endif
                <button type="button" class="btn-secondary !min-h-8" @click="toggle()" :aria-label="dark ? 'Modo claro' : 'Modo oscuro'"><span x-text="dark ? '☀' : '☾'"></span></button>
                <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-secondary !min-h-8">Salir</button></form>
            </div>
        </header>

        @if (! $isAdmin && isset($subscriptionMode) && $subscriptionMode !== 'full')
            <div role="alert" class="border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-900">
                {{ \App\Services\SubscriptionService::modeMessage($subscriptionMode) }}
            </div>
        @endif

        <main class="mx-auto max-w-7xl p-4 sm:p-6">
            @yield('content')
        </main>
        <footer class="px-6 pb-6 text-center text-xs text-neutral-500">{{ config('fidelidad.brand.name') }} · {{ config('fidelidad.brand.signature') }}</footer>
    </div>
</div>
<x-flash />
</body>
</html>
