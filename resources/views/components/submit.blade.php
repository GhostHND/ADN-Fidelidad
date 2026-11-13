@props(['label' => 'Guardar', 'loading' => 'Guardando…', 'class' => 'btn-primary'])
<button type="submit" class="{{ $class }}" :disabled="busy" {{ $attributes }}>
    <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".3" stroke-width="4"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4"/></svg>
    <span x-show="!busy">{{ $label }}</span><span x-show="busy" x-cloak>{{ $loading }}</span>
</button>
