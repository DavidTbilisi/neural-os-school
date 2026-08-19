<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-full bg-danger px-5 py-2.5 text-sm font-semibold text-white transition-opacity duration-fast hover:opacity-90 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
