@extends('layouts.app')
@section('title', $program->exists ? 'Editar tarjeta' : 'Nueva tarjeta')
@section('content')
@php
    $d = $program->design ?? [];
    $assetUrl = fn ($id) => $id ? optional($assets->firstWhere('id', $id), fn ($a) => Storage::disk('public')->url($a->path)) : null;
@endphp
<x-page-header :title="$program->exists ? $program->name : 'Nueva tarjeta'" :subtitle="$program->exists ? 'Estado: '.__('fidelidad.status.'.$program->status).' · versión '.$program->current_version : 'Define reglas y diseño. Se guardará como borrador.'">
    @if ($program->exists)
        @foreach (['active' => 'Activar', 'paused' => 'Pausar', 'archived' => 'Archivar'] as $st => $txt)
            @if ($program->status !== $st && $subscriptionMode === 'full')
                <form method="POST" action="{{ route('app.programs.status', $program) }}" x-data="guardedForm" @submit="submit">@csrf<input type="hidden" name="status" value="{{ $st }}"><x-submit :label="$txt" class="{{ $st === 'active' ? 'btn-primary' : 'btn-secondary' }}" /></form>
            @endif
        @endforeach
    @endif
</x-page-header>

<div x-data="{
        title: @js($d['card_title'] ?? $program->name), bg: @js($d['background_color'] ?? '#1F2937'), fg: @js($d['foreground_color'] ?? '#FFFFFF'), lb: @js($d['label_color'] ?? '#0FA7B4'),
        goal: {{ (int) old('stamps_goal', $program->stamps_goal) }}, demo: 3, name: @js($program->name),
        on: @js($assetUrl($d['stamp_on_asset_id'] ?? null)), off: @js($assetUrl($d['stamp_off_asset_id'] ?? null)),
        rule: @js(old('rule_type', $program->rule_type)),
        upload(kind, e) {
            const f = e.target.files[0]; if (!f) return;
            const fd = new FormData(); fd.append('file', f); fd.append('kind', kind);
            fetch('{{ route('app.media.store') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' }, body: fd })
              .then(async r => ({ ok: r.ok, d: await r.json() }))
              .then(({ ok, d }) => { if (!ok) { alert(d.message || 'No se pudo subir la imagen.'); return; }
                 const k = kind === 'stamp_on' ? 'on' : 'off'; this[k] = d.url; this.$refs[kind].value = d.id; });
        }
    }" class="grid gap-6 xl:grid-cols-3">

    <form method="POST" action="{{ $program->exists ? route('app.programs.update', $program) : route('app.programs.store') }}" x-data="guardedForm" @submit="submit" class="card space-y-4 xl:col-span-2">
        @csrf @if ($program->exists) @method('PUT') @endif
        <h2 class="font-semibold">Información y reglas</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="label" for="name">Nombre *</label><input id="name" name="name" class="input" value="{{ old('name', $program->name) }}" required x-model="name">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="stamps_goal">Sellos para completar *</label><input id="stamps_goal" type="number" min="1" max="50" name="stamps_goal" class="input" value="{{ old('stamps_goal', $program->stamps_goal) }}" required x-model.number="goal">@error('stamps_goal')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="rule_type">Cómo se ganan sellos *</label>
                <select id="rule_type" name="rule_type" class="input" x-model="rule">@foreach (trans('fidelidad.rule_type') as $k => $v)<option value="{{ $k }}" @selected(old('rule_type', $program->rule_type) === $k)>{{ $v }}</option>@endforeach</select></div>
            <div x-show="rule === 'per_amount'" x-cloak><label class="label" for="amount_per_stamp">Monto por sello ({{ $business->currency }})</label><input id="amount_per_stamp" type="number" step="0.01" min="0.01" name="amount_per_stamp" class="input" value="{{ old('amount_per_stamp', $program->amount_per_stamp) }}">@error('amount_per_stamp')<p class="error">{{ $message }}</p>@enderror<p class="help">El servidor calcula los sellos = monto ÷ este valor.</p></div>
            <div><label class="label" for="max_stamps_per_operation">Máximo de sellos por operación *</label><input id="max_stamps_per_operation" type="number" min="1" max="50" name="max_stamps_per_operation" class="input" value="{{ old('max_stamps_per_operation', $program->max_stamps_per_operation) }}" required></div>
            <div><label class="label" for="cycle_policy">Al completar la meta *</label>
                <select id="cycle_policy" name="cycle_policy" class="input">@foreach (trans('fidelidad.cycle') as $k => $v)<option value="{{ $k }}" @selected(old('cycle_policy', $program->cycle_policy) === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label class="label" for="starts_at">Vigente desde</label><input id="starts_at" type="date" name="starts_at" class="input" value="{{ old('starts_at', $program->starts_at?->toDateString()) }}"></div>
            <div><label class="label" for="ends_at">Vigente hasta</label><input id="ends_at" type="date" name="ends_at" class="input" value="{{ old('ends_at', $program->ends_at?->toDateString()) }}">@error('ends_at')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="md:col-span-2"><label class="label" for="description">Descripción</label><textarea id="description" name="description" rows="2" class="input">{{ old('description', $program->description) }}</textarea></div>
            <div class="md:col-span-2"><label class="label" for="terms">Términos y condiciones</label><textarea id="terms" name="terms" rows="3" class="input">{{ old('terms', $program->terms) }}</textarea></div>
        </div>

        <h2 class="pt-2 font-semibold">Diseño de la tarjeta</h2>
        <div class="grid gap-4 md:grid-cols-4">
            <div class="md:col-span-4"><label class="label" for="card_title">Título en la tarjeta</label><input id="card_title" name="design[card_title]" maxlength="40" class="input" x-model="title"></div>
            <div><label class="label">Fondo</label><input type="color" name="design[background_color]" x-model="bg" class="input !p-1"></div>
            <div><label class="label">Texto</label><input type="color" name="design[foreground_color]" x-model="fg" class="input !p-1"></div>
            <div><label class="label">Etiquetas</label><input type="color" name="design[label_color]" x-model="lb" class="input !p-1"></div>
            <div></div>
            <div class="md:col-span-2"><label class="label">Sello conseguido (PNG/JPG/WebP)</label>
                <input type="file" accept="image/png,image/jpeg,image/webp" class="input" @change="upload('stamp_on', $event)">
                <input type="hidden" name="design[stamp_on_asset_id]" x-ref="stamp_on" value="{{ $d['stamp_on_asset_id'] ?? '' }}"></div>
            <div class="md:col-span-2"><label class="label">Sello pendiente</label>
                <input type="file" accept="image/png,image/jpeg,image/webp" class="input" @change="upload('stamp_off', $event)">
                <input type="hidden" name="design[stamp_off_asset_id]" x-ref="stamp_off" value="{{ $d['stamp_off_asset_id'] ?? '' }}"></div>
        </div>
        @if ($program->exists)<div><label class="label" for="change_note">Nota del cambio (queda en el historial de versiones)</label><input id="change_note" name="change_note" maxlength="200" class="input"></div>@endif
        @if ($errors->has('design.*'))<p class="error">Revisa los colores del diseño.</p>@endif
        <x-submit :label="$program->exists ? 'Guardar tarjeta' : 'Crear tarjeta'" />
    </form>

    <div class="space-y-4">
        <div class="card">
            <h2 class="mb-1 font-semibold">Vista previa</h2>
            <div class="mb-3 flex items-center gap-2 text-sm"><label for="demo">Sellos de ejemplo:</label><input id="demo" type="range" min="0" :max="goal" x-model.number="demo"><span x-text="demo"></span></div>

            <p class="help mb-1">Tarjeta web</p>
            <div class="rounded-xl p-4 shadow" :style="`background:${bg};color:${fg}`">
                <div class="text-xs uppercase tracking-wide" :style="`color:${lb}`" x-text="name || 'Programa'"></div>
                <div class="text-lg font-bold" x-text="title || name"></div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <template x-for="i in Math.min(goal, 50)" :key="i">
                        <span class="flex h-7 w-7 items-center justify-center overflow-hidden rounded-full border text-xs" :style="`border-color:${lb};${i <= demo ? 'background:' + lb : ''}`">
                            <img x-show="i <= demo && on" :src="on" alt="" class="h-full w-full object-cover"><img x-show="i > demo && off" :src="off" alt="" class="h-full w-full object-cover">
                            <span x-show="i <= demo && !on" aria-hidden="true">✓</span>
                        </span>
                    </template>
                </div>
            </div>

            <p class="help mb-1 mt-4">Apple Wallet (storeCard)</p>
            <div class="rounded-2xl p-3 shadow" :style="`background:${bg};color:${fg}`">
                <div class="flex justify-between text-xs"><span class="font-semibold" x-text="title || name"></span><span :style="`color:${lb}`">SELLOS <b :style="`color:${fg}`" x-text="demo + '/' + goal"></b></span></div>
                <div class="my-2 h-10 rounded" style="background:rgba(255,255,255,.12)"></div>
                <div class="mx-auto h-14 w-14 rounded bg-white"></div>
            </div>

            <p class="help mb-1 mt-4">Google Wallet (loyalty)</p>
            <div class="overflow-hidden rounded-2xl shadow" :style="`background:${bg};color:${fg}`">
                <div class="p-3 text-sm font-bold" x-text="title || name"></div>
                <div class="px-3 pb-3 text-xs" :style="`color:${lb}`">Sellos: <b :style="`color:${fg}`" x-text="demo + ' de ' + goal"></b></div>
                <div class="h-8 bg-white/20"></div>
            </div>
            <p class="help mt-3">Las vistas son aproximaciones: cada plataforma aplica su propio formato al instalar el pase.</p>
        </div>
    </div>
</div>

@if ($program->exists)
<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <div class="card">
        <h2 class="mb-3 font-semibold">Recompensas</h2>
        @forelse ($program->rewards as $r)
            <details class="border-b border-neutral-100 py-2 dark:border-neutral-800">
                <summary class="cursor-pointer text-sm"><strong>{{ $r->name }}</strong> · a los {{ $r->stamps_required }} sellos · {{ __('fidelidad.status.'.$r->status) }}</summary>
                <form method="POST" action="{{ route('app.rewards.update', $r) }}" x-data="guardedForm" @submit="submit" class="mt-2 grid gap-2 md:grid-cols-2">
                    @csrf @method('PUT') @include('business.programs._reward', ['r' => $r])
                    <x-submit label="Guardar recompensa" class="btn-secondary" />
                </form>
            </details>
        @empty <x-empty text="Agrega al menos una recompensa para poder activar la tarjeta." /> @endforelse
        <form method="POST" action="{{ route('app.rewards.store', $program) }}" x-data="guardedForm" @submit="submit" class="mt-4 grid gap-2 border-t border-neutral-100 pt-4 md:grid-cols-2 dark:border-neutral-800">
            @csrf <h3 class="text-sm font-semibold md:col-span-2">Nueva recompensa</h3>
            @include('business.programs._reward', ['r' => null])
            <x-submit label="Agregar recompensa" loading="Agregando…" />
        </form>
    </div>

    <div class="space-y-6">
        <div class="card">
            <h2 class="mb-3 font-semibold">Reglas de sellos y promociones</h2>
            <p class="help mb-2">Se usan cuando la tarjeta otorga sellos por servicio o promoción. Cada regla tiene su vigencia.</p>
            @foreach ($program->stampRules as $sr)
                <form method="POST" action="{{ route('app.rules.update', $sr) }}" x-data="guardedForm" @submit="submit" class="mb-2 grid gap-2 md:grid-cols-6">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $sr->name }}" class="input md:col-span-2" aria-label="Nombre" required>
                    <select name="type" class="input" aria-label="Tipo"><option value="service" @selected($sr->type === 'service')>Servicio</option><option value="promotional" @selected($sr->type === 'promotional')>Promoción</option></select>
                    <input type="number" min="1" max="50" name="stamps" value="{{ $sr->stamps }}" class="input" aria-label="Sellos" required>
                    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" @checked($sr->is_active)> Activa</label>
                    <x-submit label="Guardar" class="btn-secondary" />
                    <input type="date" name="starts_at" value="{{ $sr->starts_at?->toDateString() }}" class="input md:col-span-3" aria-label="Desde">
                    <input type="date" name="ends_at" value="{{ $sr->ends_at?->toDateString() }}" class="input md:col-span-3" aria-label="Hasta">
                </form>
            @endforeach
            <form method="POST" action="{{ route('app.rules.store', $program) }}" x-data="guardedForm" @submit="submit" class="mt-3 grid gap-2 border-t border-neutral-100 pt-3 md:grid-cols-5 dark:border-neutral-800">
                @csrf
                <input name="name" class="input md:col-span-2" placeholder="Ej. Doble sello martes" aria-label="Nombre de la regla" required>
                <select name="type" class="input" aria-label="Tipo"><option value="service">Servicio</option><option value="promotional">Promoción</option></select>
                <input type="number" min="1" max="50" name="stamps" value="1" class="input" aria-label="Sellos" required>
                <x-submit label="Agregar" loading="Agregando…" />
                <input type="date" name="starts_at" class="input md:col-span-2" aria-label="Desde"><input type="date" name="ends_at" class="input md:col-span-3" aria-label="Hasta">
            </form>
        </div>

        <div class="card">
            <h2 class="mb-3 font-semibold">Historial de versiones</h2>
            @foreach ($program->versions as $v)
                <div class="border-b border-neutral-100 py-2 text-sm dark:border-neutral-800">
                    <strong>v{{ $v->version }}</strong> · {{ $v->created_at->format('d/m/Y H:i') }} — {{ $v->change_note }}
                </div>
            @endforeach
            <p class="help mt-2">Apple: los cambios se reflejan al actualizar el pase (puede tardar). Google: se actualizan por API.</p>
        </div>
    </div>
</div>
@endif
@endsection
