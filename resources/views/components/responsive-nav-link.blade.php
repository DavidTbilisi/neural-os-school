@props(['active'])

@php
// Rows on the black bar — a filled chip marks the active one, no left rule.
$classes = ($active ?? false)
            ? 'mx-2 block w-auto rounded-md bg-white/15 px-3 py-2 text-start text-base font-semibold text-bar-fg transition-colors duration-fast'
            : 'mx-2 block w-auto rounded-md px-3 py-2 text-start text-base font-medium text-bar-fg/70 transition-colors duration-fast hover:bg-white/10 hover:text-bar-fg';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
