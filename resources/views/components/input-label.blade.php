@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-fg']) }}>
    {{ $value ?? $slot }}
</label>
