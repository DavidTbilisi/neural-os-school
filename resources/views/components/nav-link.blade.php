@props(['active'])

@php
// Pills on the black bar, not underlines — the active item is a filled chip.
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-full bg-white/15 px-3 py-1.5 text-sm font-semibold text-bar-fg transition-colors duration-fast'
            : 'inline-flex items-center rounded-full px-3 py-1.5 text-sm text-bar-fg/70 transition-colors duration-fast hover:bg-white/10 hover:text-bar-fg';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
