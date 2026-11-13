@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])
<div {{ $attributes->only('class') }}>
    <label for="f-{{ $name }}" class="label">{{ $label }}@if ($required) <span class="text-red-500" aria-hidden="true">*</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="f-{{ $name }}" name="{{ $name }}" rows="3" class="input" @required($required)>{{ old($name, $value) }}</textarea>
    @else
        <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" class="input" @required($required) {{ $attributes->except('class') }}>
    @endif
    @if ($help)<p class="help">{{ $help }}</p>@endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
