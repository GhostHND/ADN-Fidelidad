@props(['action', 'message' => '¿Confirmas esta acción?', 'button' => 'Confirmar', 'label' => 'Eliminar', 'method' => 'DELETE', 'danger' => true])
<div x-data="{ open: false }" class="inline">
    <button type="button" class="{{ $danger ? 'btn-secondary text-red-600' : 'btn-secondary' }} !min-h-8 !px-3" @click="open = true">{{ $label }}</button>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" @keydown.escape.window="open = false">
        <div class="card w-full max-w-sm" @click.outside="open = false">
            <p class="mb-4 text-sm">{{ $message }}</p>
            <form method="POST" action="{{ $action }}" x-data="guardedForm" @submit="submit" class="flex justify-end gap-2">
                @csrf @method($method)
                <button type="button" class="btn-secondary" @click="open = false">Cancelar</button>
                <x-submit :label="$button" :class="$danger ? 'btn-danger' : 'btn-primary'" />
            </form>
        </div>
    </div>
</div>
