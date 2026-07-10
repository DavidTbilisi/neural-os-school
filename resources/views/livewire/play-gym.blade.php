<div class="max-w-2xl mx-auto">
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('gyms') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; All gyms</a>
        @if ($gym->course)
            <a href="{{ route('courses.show', $gym->course->slug) }}" class="text-sm text-indigo-700 hover:underline">{{ $gym->course->title }} &rarr;</a>
        @endif
    </div>

    {{-- INTRO ---------------------------------------------------------------}}
    @if ($phase === 'intro')
        <div class="rounded-xl border border-gray-100 bg-white p-6 text-center">
            <h1 class="text-2xl font-bold tracking-tight">{{ $gym->title }}</h1>
            @if ($gym->target_reflex)
                <p class="mt-3 text-gray-600">{{ $gym->target_reflex }}</p>
            @endif
            <div class="mt-4 flex items-center justify-center gap-3 text-sm text-gray-500">
                <span>{{ $rounds ?: $gym->round_count }} rounds</span>
                <span aria-hidden="true">·</span>
                <span>{{ $gym->timer_seconds }}s each</span>
                <span aria-hidden="true">·</span>
                <span>promote at {{ round($gym->promote_accuracy * 100) }}%</span>
            </div>
            <button wire:click="start"
                    class="mt-6 rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                Start session
            </button>
        </div>

    {{-- PROMPT (timed round) ------------------------------------------------}}
    @elseif ($phase === 'prompt' && $item)
        <div wire:key="round-{{ $index }}"
             x-data="{
                total: {{ $gym->timer_seconds }},
                remaining: {{ $gym->timer_seconds }},
                t0: 0, iv: null,
                init() {
                    this.t0 = performance.now();
                    this.iv = setInterval(() => {
                        this.remaining = Math.max(0, this.total - (performance.now() - this.t0) / 1000);
                        if (this.remaining <= 0) { this.stop(); $wire.answer(null, this.total * 1000); }
                    }, 100);
                },
                stop() { if (this.iv) { clearInterval(this.iv); this.iv = null; } },
                pick(choice) { this.stop(); $wire.answer(choice, Math.round(performance.now() - this.t0)); },
                destroy() { this.stop(); }
             }">
            <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                <span>Round {{ $round }} / {{ $rounds }}</span>
                <span x-text="remaining.toFixed(1) + 's'"></span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-gray-100 mb-5 overflow-hidden">
                <div class="h-1.5 rounded-full bg-indigo-500 transition-none"
                     :style="`width: ${(remaining / total) * 100}%`"></div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6">
                <p class="text-xs uppercase tracking-wide text-gray-400 mb-2">Which pattern?</p>
                <p class="text-lg text-gray-900">{{ $item->prompt }}</p>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($item->choices as $choice)
                    <button type="button" @click="pick(@js($choice))"
                            class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-left text-gray-800 hover:border-indigo-400 hover:bg-indigo-50">
                        {{ $choice }}
                    </button>
                @endforeach
            </div>
        </div>

    {{-- FEEDBACK ------------------------------------------------------------}}
    @elseif ($phase === 'feedback' && $feedback && $item)
        <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
            <span>Round {{ $round }} / {{ $rounds }}</span>
        </div>

        @php($ok = $feedback['correct'])
        <div class="rounded-xl border p-6 {{ $ok ? 'border-emerald-200 bg-emerald-50/50' : 'border-rose-200 bg-rose-50/50' }}">
            <div class="text-sm font-semibold {{ $ok ? 'text-emerald-700' : 'text-rose-700' }}">
                @if ($ok) ✓ Correct
                @elseif (is_null($feedback['selected'])) ⏱ Time up — it was {{ $feedback['answer'] }}
                @else ✗ Not quite — you chose {{ $feedback['selected'] }}
                @endif
            </div>
            <p class="mt-1 text-gray-900 font-medium">{{ $item->prompt }}</p>
            <p class="mt-2 text-sm"><span class="text-gray-500">Answer:</span> <span class="font-semibold text-gray-900">{{ $feedback['answer'] }}</span></p>
            @if ($feedback['explanation'])
                <p class="mt-2 text-sm text-gray-700">{{ $feedback['explanation'] }}</p>
            @endif
            @if ($feedback['detail'])
                <p class="mt-2 text-sm text-gray-500 border-l-2 border-gray-200 pl-3">{{ $feedback['detail'] }}</p>
            @endif
        </div>

        <button wire:click="next"
                class="mt-4 w-full rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
            {{ $round >= $rounds ? 'See summary' : 'Next round' }}
        </button>

    {{-- SUMMARY -------------------------------------------------------------}}
    @elseif ($phase === 'summary' && $summary)
        @php($s = $summary['session'])
        <div class="rounded-xl border border-gray-100 bg-white p-6 text-center">
            <h1 class="text-xl font-bold tracking-tight">
                {{ $summary['passed'] ? 'Session complete — reflex is stabilizing.' : 'Session complete — keep drilling.' }}
            </h1>

            <div class="mt-5 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-gray-50 p-3">
                    <div class="text-2xl font-bold {{ $summary['passed'] ? 'text-emerald-600' : 'text-gray-900' }}">{{ round($s->accuracy * 100) }}%</div>
                    <div class="text-xs text-gray-500">{{ $s->correct }}/{{ $s->total }} correct</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-3">
                    <div class="text-2xl font-bold text-gray-900">{{ $s->median_latency_ms ? number_format($s->median_latency_ms / 1000, 1) : '—' }}s</div>
                    <div class="text-xs text-gray-500">median response</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-3">
                    <div class="text-2xl font-bold text-indigo-600">{{ $s->stage_code ?? '—' }}</div>
                    <div class="text-xs text-gray-500">{{ $summary['stageLabel'] ?? 'stage' }}</div>
                </div>
            </div>

            @if ($summary['confusion'])
                <p class="mt-4 text-sm text-gray-600">
                    Most confused: <span class="font-medium text-rose-700">{{ $summary['confusion']['pair'] }}</span>
                    <span class="text-gray-400">(×{{ $summary['confusion']['count'] }})</span>
                </p>
            @endif

            <div class="mt-6 flex items-center justify-center gap-3">
                <button wire:click="start" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Play again</button>
                @if ($gym->course)
                    <a href="{{ route('courses.show', $gym->course->slug) }}" class="rounded-md border border-gray-200 px-5 py-2 text-sm text-gray-700 hover:bg-gray-50">Back to course</a>
                @endif
            </div>
        </div>
    @endif
</div>
