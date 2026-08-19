<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center rounded-full border border-border bg-surface px-5 py-2.5 text-sm font-semibold text-fg transition-colors duration-fast hover:bg-surface-sunken disabled:opacity-50']) }}>
    {{ $slot }}
</button>
