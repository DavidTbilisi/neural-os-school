{{--
    "See it N ways" — the ≥4 canvas representations of a page, built by
    App\Services\Wiki\RepresentationBuilder and passed in as $reps.

    Tabs are Alpine (bundled with Livewire). Only the first panel (the concept
    graph, which uses echarts) is visible at load — non-active panels carry a
    server-side `display:none` so echarts.js sizes the graph correctly on
    DOMContentLoaded and there is no un-Alpined flash. echarts + mermaid mount
    themselves (see resources/js/echarts.js, wiki-diagrams.js).
--}}
@php
    $meta = [
        'graph'  => ['glyph' => '🕸️', 'name' => 'Concept graph',  'kind' => 'analytical'],
        'flow'   => ['glyph' => '🌳', 'name' => 'Structure flow',  'kind' => 'analytical'],
        'matrix' => ['glyph' => '▦',  'name' => 'Matrix',          'kind' => 'analytical'],
        'palace' => ['glyph' => '🏛️', 'name' => 'Memory palace',   'kind' => 'mnemonic'],
        'scene'  => ['glyph' => '🎬', 'name' => 'Frozen scene',     'kind' => 'mnemonic'],
    ];
@endphp

<section
    x-data="{ tab: @js($reps['panels'][0]) }"
    class="mt-8 rounded-lg border border-border bg-surface px-6 py-6 sm:px-10"
    aria-label="Visual representations"
>
    <div class="mb-5 flex items-baseline gap-3">
        <h2 class="font-display text-2xl font-bold text-fg">See it {{ count($reps['panels']) }} ways</h2>
        <span class="text-sm text-subtle">the same note, four+ representations</span>
    </div>

    <nav role="tablist" class="flex flex-wrap gap-1 border-b border-border">
        @foreach ($reps['panels'] as $p)
            <button
                type="button"
                role="tab"
                @click="tab = @js($p)"
                :aria-selected="tab === @js($p) ? 'true' : 'false'"
                class="group -mb-px inline-flex items-center gap-2 rounded-t-md border border-transparent px-4 py-2.5 text-sm font-medium text-muted transition
                       hover:text-fg
                       aria-selected:border-border aria-selected:border-b-2 aria-selected:border-b-primary aria-selected:bg-surface aria-selected:text-primary-subtle-fg"
            >
                <span class="text-base leading-none">{{ $meta[$p]['glyph'] }}</span>
                <span>{{ $meta[$p]['name'] }}</span>
                <span class="rounded-full border border-border px-1.5 py-px text-[10px] uppercase tracking-wider text-subtle">{{ $meta[$p]['kind'] }}</span>
            </button>
        @endforeach
    </nav>

    <div class="pt-5">
        {{-- 1. Concept graph (echarts force) --}}
        @if (in_array('graph', $reps['panels']))
            <div x-show="tab === 'graph'" @if ($reps['panels'][0] !== 'graph') style="display:none" @endif>
                <p class="mb-3 text-sm text-muted">Linked concepts — drag to explore; hover to focus a neighbourhood.</p>
                <div class="rounded-xl border border-border overflow-hidden">
                    <x-echart :option="$reps['graph']" height="460px" />
                </div>
            </div>
        @endif

        {{-- 2. Structure flow (mermaid) — rendered lazily on first reveal, because
             mermaid cannot lay out inside a display:none tab (window.renderMermaidInto). --}}
        @if ($reps['flow'])
            <div
                x-show="tab === 'flow'"
                style="display:none"
                x-data="{ src: @js($reps['flow']['mermaid']) }"
                x-effect="if (tab === 'flow') $nextTick(() => window.renderMermaidInto($refs.flowHost, src))"
            >
                <p class="mb-3 text-sm text-muted">The note's heading skeleton.</p>
                <div x-ref="flowHost" class="rounded-xl border border-border bg-surface-raised p-4 overflow-x-auto"></div>
            </div>
        @endif

        {{-- 2b. Matrix --}}
        @if ($reps['matrix'])
            <div x-show="tab === 'matrix'" style="display:none">
                <p class="mb-3 text-sm text-muted">{{ $reps['matrix']['heading'] }}</p>
                <div class="rounded-xl border border-border overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-surface-sunken">
                                @foreach ($reps['matrix']['columns'] as $col)
                                    <th class="border-b border-border-strong px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-subtle">{{ $col }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reps['matrix']['rows'] as $row)
                                <tr class="hover:bg-primary-subtle/30">
                                    @foreach ($row as $i => $cell)
                                        <td class="border-b border-border-subtle px-4 py-3 align-top {{ $i === 0 ? 'font-medium text-primary-subtle-fg' : 'text-fg' }}">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- 3. Memory palace --}}
        @if ($reps['palace'])
            <div x-show="tab === 'palace'" style="display:none">
                <p class="mb-3 text-sm text-muted">{{ $reps['palace']['title'] }}</p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($reps['palace']['tiles'] as $tile)
                        <div class="relative rounded-lg border border-border bg-surface-raised p-5 shadow-sm">
                            @if ($tile['badge'])
                                <span class="absolute right-3 top-3 grid h-6 w-6 place-items-center rounded-full bg-primary text-xs font-bold text-primary-fg">{{ $tile['badge'] }}</span>
                            @endif
                            <span class="mb-3 block text-3xl leading-none">{{ $tile['glyph'] }}</span>
                            <p class="mb-1 font-display text-lg text-fg">{{ $tile['label'] }}</p>
                            @if ($tile['note'])
                                <p class="text-sm text-muted">{{ $tile['note'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 4. Frozen scene --}}
        <div x-show="tab === 'scene'" style="display:none">
            <p class="mb-3 text-sm text-muted">One image to freeze the whole note.</p>
            <div class="rounded-xl border border-border bg-gradient-to-br from-primary-subtle/50 to-surface p-8">
                <h3 class="mb-7 text-center font-display text-2xl text-fg">{{ $reps['scene']['title'] }}</h3>
                @if (! empty($reps['scene']['elements']))
                    <div class="mb-7 flex flex-wrap items-stretch justify-center gap-4">
                        @foreach ($reps['scene']['elements'] as $el)
                            @if (! $loop->first && ! empty($reps['scene']['sequence']))
                                <span class="self-center text-2xl text-subtle">→</span>
                            @endif
                            <div class="flex-[0_1_200px] rounded-lg border border-border-subtle bg-surface-raised p-5 text-center shadow-sm">
                                <span class="mb-3 block text-4xl">{{ $el['glyph'] }}</span>
                                <span class="text-sm text-muted">{{ $el['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($reps['scene']['caption'])
                    <p class="mx-auto max-w-prose text-center font-display text-lg italic text-muted">{{ $reps['scene']['caption'] }}</p>
                @endif
            </div>
        </div>
    </div>
</section>
