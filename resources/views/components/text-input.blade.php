@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-md border-border bg-surface text-fg placeholder:text-subtle focus:border-ring focus:ring-ring']) }}>
