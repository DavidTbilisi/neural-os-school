<x-app-layout>
    <x-slot name="header">
        <div class="flex items-baseline gap-3">
            <h2 class="font-semibold text-xl text-fg leading-tight">Progress</h2>
            <span class="text-xs text-subtle">METER · your private measurement · last {{ $report['windowDays'] }} days</span>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @php
                $tone = fn (string $t) => [
                    'emerald' => 'bg-success-subtle text-success-fg',
                    'indigo'  => 'bg-primary-subtle text-primary-subtle-fg',
                    'amber'   => 'bg-warning-subtle text-warning-fg',
                    'rose'    => 'bg-danger-subtle text-danger-fg',
                    'gray'    => 'bg-surface-sunken text-muted',
                ][$t] ?? 'bg-surface-sunken text-muted';
            @endphp

            {{-- Daily Glance --------------------------------------------------}}
            <div class="border border-border bg-surface sm:rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-fg">Today at a glance</h3>
                    <span class="text-xs text-subtle">{{ now()->format('D, M j') }}</span>
                </div>
                @php $g = $report['glance']; @endphp
                {{-- One hue per metric, fixed — the tile's color is part of how
                     you find it, so it must not shuffle between visits. --}}
                @php
                    $tiles = [
                        [0, $g['reps'], 'gym reps'],
                        [1, $g['accuracy'] !== null ? round($g['accuracy'] * 100).'%' : '—', 'accuracy'],
                        [3, $g['sessions'], 'sessions'],
                        [5, $g['lessons'], 'lessons done'],
                        [4, $g['reviewsDue'], 'reviews due'],
                    ];
                @endphp
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
                    @foreach ($tiles as [$hueIndex, $value, $label])
                        @php $hue = \App\Support\Palette::nth($hueIndex); @endphp
                        <div class="rounded-lg {{ $hue['fill'] }} p-4">
                            <div class="text-2xl font-bold {{ $hue['text'] }}">{{ $value }}</div>
                            <div class="text-xs opacity-80 {{ $hue['text'] }}">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            @unless ($report['hasData'])
                <div class="border border-border bg-surface sm:rounded-lg p-10 text-center text-muted">
                    No signal yet. <a href="{{ route('gyms') }}" class="text-primary-subtle-fg hover:underline">Play a gym</a>
                    or <a href="{{ route('courses') }}" class="text-primary-subtle-fg hover:underline">work through a course</a> and your METER fills in.
                </div>
            @endunless

            {{-- Performance ---------------------------------------------------}}
            @if (! empty($report['performance']))
                <div class="border border-border bg-surface sm:rounded-lg p-6">
                    <h3 class="font-semibold text-fg mb-1">Recognition performance</h3>
                    <p class="text-xs text-muted mb-4">Accuracy + speed per gym, against your promote target. A metric needs ≥{{ \App\Services\Meter\Report::MIN_SIGNAL }} reps to be trusted.</p>
                    <div class="space-y-4">
                        @foreach ($report['performance'] as $p)
                            <div class="rounded-lg border border-border p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <a href="{{ route('gyms.play', $p['slug']) }}" class="font-medium text-primary-subtle-fg hover:underline">{{ $p['title'] }}</a>
                                    <span class="text-xs rounded-full px-2 py-0.5 {{ $tone($p['verdict']['tone']) }}">{{ $p['verdict']['label'] }}</span>
                                </div>

                                @if ($p['insufficient'])
                                    <p class="mt-2 text-sm text-muted">{{ $p['n'] }} / {{ \App\Services\Meter\Report::MIN_SIGNAL }} reps — keep drilling to get a read.</p>
                                @else
                                    <div class="mt-3 flex flex-wrap items-end gap-6">
                                        <div>
                                            <div class="text-2xl font-bold text-fg">{{ round($p['accuracy'] * 100) }}%</div>
                                            <div class="text-xs text-muted">accuracy · target {{ round($p['target'] * 100) }}% · n={{ $p['n'] }}</div>
                                        </div>
                                        <div>
                                            <div class="text-2xl font-bold text-fg">{{ $p['medianLatencyMs'] ? number_format($p['medianLatencyMs'] / 1000, 1).'s' : '—' }}</div>
                                            <div class="text-xs text-muted">median response{{ $p['latencyRead'] ? ' · '.$p['latencyRead'] : '' }}</div>
                                        </div>
                                        @if ($p['stage'])
                                            <div><div class="text-2xl font-bold text-primary">{{ $p['stage'] }}</div><div class="text-xs text-muted">stage</div></div>
                                        @endif
                                        @if (count($p['trend']) > 1)
                                            <div class="ml-auto">
                                                <div class="flex items-end gap-1 h-10" title="Accuracy per session">
                                                    @foreach ($p['trend'] as $v)
                                                        <div class="w-2 rounded-t bg-primary/70" style="height: {{ max(4, $v) }}%"></div>
                                                    @endforeach
                                                </div>
                                                <div class="text-xs text-subtle text-right mt-1">by session</div>
                                            </div>
                                        @endif
                                    </div>
                                    @if ($p['breach'])
                                        <p class="mt-2 text-sm text-danger-fg">⚠ Below floor for 3+ sessions — drop to easier reps and rebuild the reflex.</p>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Accuracy by gym (Apache ECharts) ------------------------------}}
            @php
                $chartRows = collect($report['performance'] ?? [])->reject(fn ($p) => $p['insufficient']);
            @endphp
            @if ($chartRows->isNotEmpty())
                <div class="border border-border bg-surface sm:rounded-lg p-6">
                    <h3 class="font-semibold text-fg mb-1">Accuracy by gym</h3>
                    <p class="text-xs text-muted mb-4">Current accuracy vs. promote target, rendered with Apache ECharts.</p>
                    <x-echart
                        :height="max(160, $chartRows->count() * 48 + 40) . 'px'"
                        :option="[
                            'grid' => ['left' => 8, 'right' => 40, 'top' => 10, 'bottom' => 8, 'containLabel' => true],
                            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
                            'xAxis' => [
                                'type' => 'value',
                                'max' => 100,
                                'axisLabel' => ['formatter' => '{value}%'],
                                'splitLine' => ['lineStyle' => ['color' => '#f1f5f9']],
                            ],
                            'yAxis' => [
                                'type' => 'category',
                                'data' => $chartRows->pluck('title')->all(),
                                'axisTick' => ['show' => false],
                            ],
                            'series' => [
                                [
                                    'name' => 'Accuracy',
                                    'type' => 'bar',
                                    'data' => $chartRows->map(fn ($p) => round($p['accuracy'] * 100))->all(),
                                    'barMaxWidth' => 22,
                                    'itemStyle' => ['color' => '#6366f1', 'borderRadius' => [0, 4, 4, 0]],
                                    'label' => ['show' => true, 'position' => 'right', 'formatter' => '{c}%'],
                                    'markLine' => [
                                        'symbol' => 'none',
                                        'lineStyle' => ['type' => 'dashed', 'color' => '#f59e0b'],
                                        'label' => ['formatter' => 'target', 'position' => 'insideEndTop', 'color' => '#b45309'],
                                        'data' => [['xAxis' => round($chartRows->avg('target') * 100)]],
                                    ],
                                ],
                            ],
                        ]"
                    />
                </div>
            @endif

            {{-- Retrieval / courses -------------------------------------------}}
            @php $r = $report['retrieval']; @endphp
            @if (! empty($r['courses']))
                <div class="border border-border bg-surface sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-fg">Course progress</h3>
                        <span class="text-xs text-muted">{{ $r['lessonsThisWeek'] }} lessons this week</span>
                    </div>
                    <ul class="space-y-3">
                        @foreach ($r['courses'] as $c)
                            <li>
                                <div class="flex items-center justify-between text-sm">
                                    <a href="{{ route('courses.show', $c['slug']) }}" class="text-primary-subtle-fg hover:underline">{{ $c['title'] }}</a>
                                    <span class="text-muted">{{ $c['done'] }}/{{ $c['total'] }} · {{ round($c['progress'] * 100) }}%{{ $c['complete'] ? ' ✓' : '' }}</span>
                                </div>
                                <div class="mt-1 h-2 w-full rounded-full bg-surface-sunken">
                                    <div class="h-2 rounded-full bg-success" style="width: {{ round($c['progress'] * 100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Module coverage (evidence) -------------------------------------}}
            @if (! empty($report['coverage']))
                <div class="border border-border bg-surface sm:rounded-lg p-6">
                    <h3 class="font-semibold text-fg mb-1">Module coverage</h3>
                    <p class="text-xs text-muted mb-4">
                        Evidence from drill telemetry, not checkboxes. A module reads as covered ✓ after
                        ≥{{ \App\Services\Meter\Report::MIN_SIGNAL }} reps across
                        ≥{{ \App\Services\Meter\Report::MIN_SESSIONS }} sessions at its target rung
                        (default L{{ \App\Support\KnowledgeLadder::DEFAULT_TARGET }} Classifiable).
                    </p>
                    <div class="space-y-5">
                        @foreach ($report['coverage'] as $cc)
                            <div>
                                @php $courseDue = collect($cc['modules'])->sum(fn ($m) => $m['retention']['due']); @endphp
                                <div class="flex items-center justify-between text-sm mb-2">
                                    <a href="{{ route('courses.show', $cc['slug']) }}" class="font-medium text-primary-subtle-fg hover:underline">{{ $cc['title'] }}</a>
                                    <span class="flex items-center gap-3">
                                        @if ($courseDue > 0 && $cc['reviewGym'])
                                            <a href="{{ route('gyms.play', $cc['reviewGym']) }}?mode=review" class="text-xs text-warning-fg hover:underline">🔁 review {{ $courseDue }} due</a>
                                        @endif
                                        @if ($cc['uninstrumented'] > 0)
                                            <span class="text-xs text-subtle">{{ $cc['uninstrumented'] }} {{ Str::plural('module', $cc['uninstrumented']) }} without drill items yet</span>
                                        @endif
                                    </span>
                                </div>
                                <ul class="space-y-2">
                                    @foreach ($cc['modules'] as $m)
                                        <li class="flex items-center justify-between gap-3 text-sm">
                                            <span class="text-fg">
                                                {{ $m['title'] }}@if ($m['covered']) <span class="text-success" title="Covered: sustained pass-accuracy evidence">✓</span>@endif
                                            </span>
                                            <span class="flex items-center gap-2 whitespace-nowrap">
                                                @if (! $m['certifiable'])
                                                    <span class="text-xs text-warning" title="{{ $m['targetRung']['standard'] }} — beyond a timed recognition drill">target L{{ $m['targetRung']['level'] }} · needs a deeper instrument</span>
                                                @elseif ($m['insufficient'])
                                                    <span class="text-xs text-subtle">{{ $m['n'] }}/{{ \App\Services\Meter\Report::MIN_SIGNAL }} reps · target L{{ $m['targetRung']['level'] }}</span>
                                                @else
                                                    <span class="text-xs text-muted">
                                                        {{ round($m['accuracy'] * 100) }}% · n={{ $m['n'] }} · {{ $m['sessions'] }} {{ Str::plural('session', $m['sessions']) }}@if ($m['rung']) · L{{ $m['rung']['level'] }} {{ $m['rung']['name'] }}@endif · target L{{ $m['targetRung']['level'] }}@if ($m['retention']['scheduled'] > 0) · <span class="{{ $m['retention']['due'] > 0 ? 'text-warning' : '' }}">retention {{ round($m['retention']['rate'] * 100) }}%@if ($m['retention']['due'] > 0) ({{ $m['retention']['due'] }} due)@endif</span>@endif
                                                    </span>
                                                @endif
                                                <span class="text-xs rounded-full px-2 py-0.5 {{ $tone($m['verdict']['tone']) }}">{{ $m['verdict']['label'] }}</span>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Totals + Goodhart guard ---------------------------------------}}
            <div class="grid gap-4 sm:grid-cols-4">
                @php $t = $report['totals']; @endphp
                <div class="border border-border bg-surface sm:rounded-lg p-4 text-center"><div class="text-xl font-bold text-fg">{{ $t['reps'] }}</div><div class="text-xs text-muted">reps ({{ $report['windowDays'] }}d)</div></div>
                <div class="border border-border bg-surface sm:rounded-lg p-4 text-center"><div class="text-xl font-bold text-fg">{{ $t['sessions'] }}</div><div class="text-xs text-muted">sessions</div></div>
                <div class="border border-border bg-surface sm:rounded-lg p-4 text-center"><div class="text-xl font-bold text-fg">{{ $t['activeDays'] }}</div><div class="text-xs text-muted">active days</div></div>
                <div class="border border-border bg-surface sm:rounded-lg p-4 text-center"><div class="text-xl font-bold text-fg">{{ $r['lessonsInWindow'] }}</div><div class="text-xs text-muted">lessons</div></div>
            </div>

            <p class="text-center text-xs text-subtle">These are signals, not a scoreboard. Ask yourself: what felt hardest this week — and does the data agree?</p>
        </div>
    </div>
</x-app-layout>
