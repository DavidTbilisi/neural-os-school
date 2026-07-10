<x-app-layout>
    <x-slot name="header">
        <div class="flex items-baseline gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Progress</h2>
            <span class="text-xs text-gray-400">METER · your private measurement · last {{ $report['windowDays'] }} days</span>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @php
                $tone = fn (string $t) => [
                    'emerald' => 'bg-emerald-50 text-emerald-700',
                    'indigo'  => 'bg-indigo-50 text-indigo-700',
                    'amber'   => 'bg-amber-50 text-amber-700',
                    'rose'    => 'bg-rose-50 text-rose-700',
                    'gray'    => 'bg-gray-100 text-gray-600',
                ][$t] ?? 'bg-gray-100 text-gray-600';
            @endphp

            {{-- Daily Glance --------------------------------------------------}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Today at a glance</h3>
                    <span class="text-xs text-gray-400">{{ now()->format('D, M j') }}</span>
                </div>
                @php($g = $report['glance'])
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                    <div><div class="text-2xl font-bold text-gray-900">{{ $g['reps'] }}</div><div class="text-xs text-gray-500">gym reps</div></div>
                    <div><div class="text-2xl font-bold text-gray-900">{{ $g['accuracy'] !== null ? round($g['accuracy'] * 100).'%' : '—' }}</div><div class="text-xs text-gray-500">accuracy</div></div>
                    <div><div class="text-2xl font-bold text-gray-900">{{ $g['sessions'] }}</div><div class="text-xs text-gray-500">sessions</div></div>
                    <div><div class="text-2xl font-bold text-gray-900">{{ $g['lessons'] }}</div><div class="text-xs text-gray-500">lessons done</div></div>
                </div>
            </div>

            @unless ($report['hasData'])
                <div class="bg-white shadow-sm sm:rounded-lg p-10 text-center text-gray-500">
                    No signal yet. <a href="{{ route('gyms') }}" class="text-indigo-700 hover:underline">Play a gym</a>
                    or <a href="{{ route('courses') }}" class="text-indigo-700 hover:underline">work through a course</a> and your METER fills in.
                </div>
            @endunless

            {{-- Performance ---------------------------------------------------}}
            @if (! empty($report['performance']))
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-1">Recognition performance</h3>
                    <p class="text-xs text-gray-500 mb-4">Accuracy + speed per gym, against your promote target. A metric needs ≥{{ \App\Services\Meter\Report::MIN_SIGNAL }} reps to be trusted.</p>
                    <div class="space-y-4">
                        @foreach ($report['performance'] as $p)
                            <div class="rounded-lg border border-gray-100 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <a href="{{ route('gyms.play', $p['slug']) }}" class="font-medium text-indigo-700 hover:underline">{{ $p['title'] }}</a>
                                    <span class="text-xs rounded-full px-2 py-0.5 {{ $tone($p['verdict']['tone']) }}">{{ $p['verdict']['label'] }}</span>
                                </div>

                                @if ($p['insufficient'])
                                    <p class="mt-2 text-sm text-gray-500">{{ $p['n'] }} / {{ \App\Services\Meter\Report::MIN_SIGNAL }} reps — keep drilling to get a read.</p>
                                @else
                                    <div class="mt-3 flex flex-wrap items-end gap-6">
                                        <div>
                                            <div class="text-2xl font-bold text-gray-900">{{ round($p['accuracy'] * 100) }}%</div>
                                            <div class="text-xs text-gray-500">accuracy · target {{ round($p['target'] * 100) }}% · n={{ $p['n'] }}</div>
                                        </div>
                                        <div>
                                            <div class="text-2xl font-bold text-gray-900">{{ $p['medianLatencyMs'] ? number_format($p['medianLatencyMs'] / 1000, 1).'s' : '—' }}</div>
                                            <div class="text-xs text-gray-500">median response{{ $p['latencyRead'] ? ' · '.$p['latencyRead'] : '' }}</div>
                                        </div>
                                        @if ($p['stage'])
                                            <div><div class="text-2xl font-bold text-indigo-600">{{ $p['stage'] }}</div><div class="text-xs text-gray-500">stage</div></div>
                                        @endif
                                        @if (count($p['trend']) > 1)
                                            <div class="ml-auto">
                                                <div class="flex items-end gap-1 h-10" title="Accuracy per session">
                                                    @foreach ($p['trend'] as $v)
                                                        <div class="w-2 rounded-t bg-indigo-400" style="height: {{ max(4, $v) }}%"></div>
                                                    @endforeach
                                                </div>
                                                <div class="text-xs text-gray-400 text-right mt-1">by session</div>
                                            </div>
                                        @endif
                                    </div>
                                    @if ($p['breach'])
                                        <p class="mt-2 text-sm text-rose-700">⚠ Below floor for 3+ sessions — drop to easier reps and rebuild the reflex.</p>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Retrieval / courses -------------------------------------------}}
            @php($r = $report['retrieval'])
            @if (! empty($r['courses']))
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900">Course progress</h3>
                        <span class="text-xs text-gray-500">{{ $r['lessonsThisWeek'] }} lessons this week</span>
                    </div>
                    <ul class="space-y-3">
                        @foreach ($r['courses'] as $c)
                            <li>
                                <div class="flex items-center justify-between text-sm">
                                    <a href="{{ route('courses.show', $c['slug']) }}" class="text-indigo-700 hover:underline">{{ $c['title'] }}</a>
                                    <span class="text-gray-500">{{ $c['done'] }}/{{ $c['total'] }} · {{ round($c['progress'] * 100) }}%{{ $c['complete'] ? ' ✓' : '' }}</span>
                                </div>
                                <div class="mt-1 h-2 w-full rounded-full bg-gray-100">
                                    <div class="h-2 rounded-full bg-emerald-500" style="width: {{ round($c['progress'] * 100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Totals + Goodhart guard ---------------------------------------}}
            <div class="grid gap-4 sm:grid-cols-4">
                @php($t = $report['totals'])
                <div class="bg-white shadow-sm sm:rounded-lg p-4 text-center"><div class="text-xl font-bold text-gray-900">{{ $t['reps'] }}</div><div class="text-xs text-gray-500">reps ({{ $report['windowDays'] }}d)</div></div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4 text-center"><div class="text-xl font-bold text-gray-900">{{ $t['sessions'] }}</div><div class="text-xs text-gray-500">sessions</div></div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4 text-center"><div class="text-xl font-bold text-gray-900">{{ $t['activeDays'] }}</div><div class="text-xs text-gray-500">active days</div></div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4 text-center"><div class="text-xl font-bold text-gray-900">{{ $r['lessonsInWindow'] }}</div><div class="text-xs text-gray-500">lessons</div></div>
            </div>

            <p class="text-center text-xs text-gray-400">These are signals, not a scoreboard. Ask yourself: what felt hardest this week — and does the data agree?</p>
        </div>
    </div>
</x-app-layout>
