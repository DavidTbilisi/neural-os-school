<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-primary-fg transition-colors duration-fast hover:bg-primary-hover active:bg-primary-active disabled:opacity-50']) }}>
    {{ $slot }}
</button>
