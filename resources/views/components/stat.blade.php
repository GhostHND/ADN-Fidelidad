@props(['label', 'value', 'hint' => null])
<div class="card">
    <div class="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $label }}</div>
    <div class="mt-1 text-3xl font-bold">{{ $value }}</div>
    @if ($hint)<div class="mt-1 text-xs text-neutral-500">{{ $hint }}</div>@endif
</div>
