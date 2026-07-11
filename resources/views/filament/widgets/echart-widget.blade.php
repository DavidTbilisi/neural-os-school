<x-filament-widgets::widget>
    <x-filament::section>
        @if ($heading)
            <x-slot name="heading">{{ $heading }}</x-slot>
        @endif

        {{--
            wire:ignore keeps Livewire from morphing the canvas out from under
            ECharts when the widget refreshes/polls. x-init runs when Alpine
            mounts this element — which covers both a full page load and a lazy
            widget being inserted after the fact. If the ECharts module hasn't
            finished loading yet, we wait for its `echarts:ready` event.
        --}}
        <div
            wire:ignore
            x-data="{
                render() {
                    const mount = () => window.mountEChart(this.$refs.canvas, @js($option));
                    window.mountEChart
                        ? mount()
                        : window.addEventListener('echarts:ready', mount, { once: true });
                },
            }"
            x-init="render()"
        >
            <div x-ref="canvas" class="w-full" style="height: {{ $height }};"></div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
