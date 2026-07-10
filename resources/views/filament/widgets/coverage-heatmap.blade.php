<x-filament::section>
    <x-slot name="heading">Coverage — pages by domain × palace</x-slot>
    <x-slot name="description">Where the {{ number_format($grand) }} pages sit across the two structural axes. Darker = more pages.</x-slot>

    <div style="overflow-x:auto">
        <table style="border-collapse:collapse;font-size:0.8rem;width:100%;min-width:640px">
            <thead>
                <tr>
                    <th style="text-align:left;padding:6px 10px;font-weight:600">Domain \ Palace</th>
                    @foreach ($palaceKeys as $p)
                        <th style="padding:6px 10px;text-align:center;font-weight:600;white-space:nowrap">{{ $palaceLabels[$p] }}</th>
                    @endforeach
                    <th style="padding:6px 10px;text-align:center;font-weight:700">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($domainRows as $did => $name)
                    <tr>
                        <td style="padding:6px 10px;white-space:nowrap">
                            <span style="opacity:.5">{{ $did ?: '–' }}.</span> {{ $name }}
                        </td>
                        @foreach ($palaceKeys as $p)
                            @php
                                $c = $matrix[$did][$p] ?? 0;
                                $a = $max > 0 ? round($c / $max, 3) : 0;
                            @endphp
                            <td style="text-align:center;padding:6px 10px;background:rgba(99,102,241,{{ $a }});
                                {{ $a > 0.5 ? 'color:#fff;' : '' }}">{{ $c ?: '' }}</td>
                        @endforeach
                        <td style="text-align:center;padding:6px 10px;font-weight:700">{{ $rowTotal[$did] ?: '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid rgba(99,102,241,.3)">
                    <td style="padding:6px 10px;font-weight:700">Total</td>
                    @foreach ($palaceKeys as $p)
                        <td style="text-align:center;padding:6px 10px;font-weight:700">{{ $colTotal[$p] ?: '' }}</td>
                    @endforeach
                    <td style="text-align:center;padding:6px 10px;font-weight:800">{{ number_format($grand) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-filament::section>
