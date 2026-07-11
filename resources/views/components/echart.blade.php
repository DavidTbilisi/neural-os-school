@props([
    'option' => [],     // ECharts option as a PHP array; serialized to JSON here
    'height' => '320px',
    'theme' => null,    // optional registered ECharts theme name
])

{{--
    Declarative ECharts container. resources/js/echarts.js finds it by the
    `data-echart` hook, reads the option from the nested JSON <script>, and
    mounts a canvas chart — on first load and after Livewire navigation.

    Usage:
        <x-echart height="320px" :option="[
            'xAxis' => ['type' => 'category', 'data' => ['A', 'B', 'C']],
            'yAxis' => ['type' => 'value'],
            'series' => [['type' => 'bar', 'data' => [5, 12, 8]]],
        ]" />

    @json escapes </script> (JSON_HEX_TAG), so arbitrary option data is safe here.
--}}
<div
    data-echart
    @if ($theme) data-echart-theme="{{ $theme }}" @endif
    {{ $attributes->merge(['class' => 'w-full']) }}
    style="height: {{ $height }};"
>
    <script type="application/json" data-echart-option>@json($option)</script>
</div>
