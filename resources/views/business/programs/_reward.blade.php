@php($r ??= null)
<input name="name" class="input" placeholder="Nombre (ej. Café gratis)" aria-label="Nombre de la recompensa" value="{{ $r?->name }}" required>
<input type="number" min="1" max="500" name="stamps_required" class="input" placeholder="Sellos requeridos" aria-label="Sellos requeridos" value="{{ $r?->stamps_required }}" required>
<select name="type" class="input" aria-label="Tipo">@foreach (trans('fidelidad.reward_type') as $k => $v)<option value="{{ $k }}" @selected(($r?->type ?? 'free_product') === $k)>{{ $v }}</option>@endforeach</select>
<input type="number" step="0.01" min="0" name="value" class="input" placeholder="Valor (opcional)" aria-label="Valor" value="{{ $r?->value }}">
<input type="date" name="valid_until" class="input" aria-label="Vigente hasta" value="{{ $r?->valid_until?->toDateString() }}">
<input type="number" min="1" name="max_redemptions" class="input" placeholder="Máx. canjes (opcional)" aria-label="Máximo de canjes" value="{{ $r?->max_redemptions }}">
<select name="status" class="input" aria-label="Estado">@foreach (['active' => 'Activa', 'inactive' => 'Inactiva', 'archived' => 'Archivada'] as $k => $v)<option value="{{ $k }}" @selected(($r?->status ?? 'active') === $k)>{{ $v }}</option>@endforeach</select>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="consumes_stamps" value="1" @checked($r?->consumes_stamps)> Descuenta sellos al canjear</label>
<textarea name="description" rows="2" class="input md:col-span-2" placeholder="Descripción" aria-label="Descripción">{{ $r?->description }}</textarea>
<textarea name="terms" rows="2" class="input md:col-span-2" placeholder="Condiciones" aria-label="Condiciones">{{ $r?->terms }}</textarea>
