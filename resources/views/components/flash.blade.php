@php($status = session('status') ?? session('success'))
<div class="fixed right-4 top-4 z-[60] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2" aria-live="polite">
    @if ($status)
        <div x-data="{ s: true }" x-show="s" x-init="setTimeout(() => s = false, 6000)" role="status" class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow">{{ $status }}</div>
    @endif
    @if (session('error'))
        <div x-data="{ s: true }" x-show="s" role="alert" class="flex justify-between gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900 shadow">
            <span>{{ session('error') }}</span><button type="button" @click="s = false" aria-label="Cerrar">✕</button>
        </div>
    @endif
    @if ($errors->any() && ! $errors->has('email') )
        <div role="alert" class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 shadow">Revisa los campos marcados en el formulario.</div>
    @endif
</div>
