{{--
    Dark-mode toggle. Alpine (bundled via Livewire) flips `.dark`/`.light` on
    <html> and persists the choice to localStorage; the no-flash script in
    partials/theme-head reads it on the next load.

    `tone="inverse"` is for the black top bar — passing colors through `class`
    instead would leave two competing text-color utilities on one element and
    let stylesheet order pick the winner.
--}}
@props(['tone' => 'default'])

@php($toneClasses = $tone === 'inverse'
    ? 'text-bar-fg/70 hover:bg-white/10 hover:text-bar-fg'
    : 'text-muted hover:bg-surface-sunken hover:text-fg')

<button
    type="button"
    x-data="{
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = !this.dark;
            const el = document.documentElement;
            el.classList.toggle('dark', this.dark);
            el.classList.toggle('light', !this.dark);
            try { localStorage.setItem('theme', this.dark ? 'dark' : 'light'); } catch (e) {}
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: this.dark } }));
        },
    }"
    x-on:click="toggle()"
    x-bind:aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
    aria-label="Toggle dark mode"
    title="Toggle theme"
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-full p-2 transition-colors duration-fast '.$toneClasses]) }}
>
    {{-- sun — shown in dark mode (click → light) --}}
    <svg x-cloak x-show="dark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.8M12 19.2V21M4.5 4.5l1.3 1.3M18.2 18.2l1.3 1.3M3 12h1.8M19.2 12H21M4.5 19.5l1.3-1.3M18.2 5.8l1.3-1.3M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" />
    </svg>
    {{-- moon — shown in light mode (click → dark) --}}
    <svg x-cloak x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.6 6.6 0 0 0 9.8 9.8Z" />
    </svg>
</button>
