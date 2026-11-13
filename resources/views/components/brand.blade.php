@php($logo = config('fidelidad.brand.logo'))
<div class="flex items-center gap-3">
    @if ($logo && file_exists(public_path($logo)))
        <img src="{{ asset($logo) }}" alt="ADN Publicidad" class="h-8 w-auto">
    @else
        {{-- Dependencia pendiente: colocar el logotipo oficial en public/{{ $logo }} --}}
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-brand text-sm font-black text-white" aria-hidden="true">A</span>
    @endif
    <div class="leading-tight">
        <div class="text-sm font-bold tracking-wide">{{ config('fidelidad.brand.name') }}</div>
        <div class="text-[11px] opacity-70">{{ config('fidelidad.brand.signature') }}</div>
    </div>
</div>
