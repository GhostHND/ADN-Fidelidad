@if ($guide)
<div x-data="firstUseGuide(@js(['steps' => $guide['steps'], 'seen' => $seen, 'url' => $saveUrl]))">
    <button type="button" class="btn-secondary !min-h-8" @click="show()">Ayuda</button>
    <dialog x-ref="dialog" aria-labelledby="guide-title" aria-describedby="guide-body"
        @cancel.prevent="finish()" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl bg-white p-0 text-neutral-900 shadow-xl backdrop:bg-black/60 dark:bg-neutral-900 dark:text-neutral-100">
        <div class="space-y-5 p-5 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-brand-600">Guía de primer uso</p>
                    <h2 id="guide-title" class="text-xl font-bold">{{ $guide['title'] }}</h2>
                </div>
                <button type="button" class="btn-secondary !min-h-8" :disabled="saving" @click="finish()" aria-label="Cerrar guía">×</button>
            </div>
            <div id="guide-body" aria-live="polite">
                <p class="mb-2 text-sm text-neutral-500" x-text="'Paso ' + (index + 1) + ' de ' + steps.length"></p>
                <h3 class="font-semibold" x-text="steps[index].title"></h3>
                <p class="mt-2 text-sm leading-6" x-text="steps[index].text"></p>
            </div>
            <p x-show="error" x-cloak role="alert" class="text-sm text-red-600 dark:text-red-400" x-text="error"></p>
            <button type="button" x-show="error" x-cloak class="text-sm underline" @click="$refs.dialog.close()">Cerrar sin guardar y volver a intentar después</button>
            <div class="flex flex-wrap justify-between gap-2">
                <button type="button" class="btn-secondary" :disabled="index === 0 || saving" @click="index--">Anterior</button>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary" :disabled="saving" @click="finish()">Cerrar guía</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="index < steps.length - 1 ? index++ : finish()"
                        x-text="index < steps.length - 1 ? 'Siguiente' : (saving ? 'Guardando…' : 'Entendido')"></button>
                </div>
            </div>
        </div>
    </dialog>
</div>
@endif
