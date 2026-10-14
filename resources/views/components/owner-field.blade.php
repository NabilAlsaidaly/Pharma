@props(['name', 'label', 'type' => 'text', 'autocomplete' => 'off', 'value' => '', 'maxlength' => 150])
<div class="space-y-2">
    <label for="{{ $name }}" class="block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" required maxlength="{{ $maxlength }}"
        value="{{ $type === 'password' ? '' : old($name, $value) }}" @if ($type === 'email' || $type === 'password') dir="ltr" @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @error($name) aria-describedby="{{ $name }}-error" @enderror
        class="w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm focus:border-emerald-700 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-700">
    @error($name)<p id="{{ $name }}-error" class="text-sm text-red-800">{{ $message }}</p>@enderror
</div>
