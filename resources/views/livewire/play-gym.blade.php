<div class="max-w-2xl mx-auto">
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('gyms') }}" class="text-sm text-muted hover:text-fg">&larr; All gyms</a>
        @if ($gym->course)
            <a href="{{ route('courses.show', $gym->course->slug) }}" class="text-sm text-primary hover:underline">{{ $gym->course->title }} &rarr;</a>
        @endif
    </div>

    {{-- INTRO ---------------------------------------------------------------}}
    @if ($phase === 'intro')
        <div class="rounded-xl border border-border bg-surface p-6 text-center">
            <h1 class="font-serif text-3xl font-semibold tracking-tight text-fg">
                {{ $gym->title }}@if ($this->isReview()) <span class="text-primary">· Review</span>@endif
            </h1>
            @if ($this->isReview())
                <p class="mt-3 text-muted">Retention pass: only cards whose schedule has ripened — oldest due first.</p>
            @elseif ($gym->target_reflex)
                <p class="mt-3 text-muted">{{ $gym->target_reflex }}</p>
            @endif
            <div class="mt-4 flex items-center justify-center gap-3 text-sm text-muted">
                @if ($this->isReview())
                    <span>{{ $dueCount }} {{ Str::plural('card', $dueCount) }} due</span>
                @else
                    <span>{{ $rounds ?: $gym->round_count }} rounds</span>
                @endif
                <span aria-hidden="true">·</span>
                <span>{{ $gym->timer_seconds }}s each</span>
                <span aria-hidden="true">·</span>
                <span>promote at {{ round($gym->promote_accuracy * 100) }}%</span>
                @if ($gym->blind_spot_floor)
                    <span aria-hidden="true">·</span>
                    <span title="Miss every item of one category and the top rung is withheld, however high the average.">no zeroed category</span>
                @endif
            </div>
            @if ($this->isReview() && $dueCount === 0)
                <p class="mt-6 text-sm text-muted">Nothing due — the schedule hasn't ripened yet.
                    <a href="{{ route('gyms.play', $gym->slug) }}" class="text-primary hover:underline">Drill instead →</a>
                </p>
            @else
                <button wire:click="start"
                        class="mt-6 rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-primary-fg hover:bg-primary-hover">
                    {{ $this->isReview() ? 'Start review' : 'Start session' }}
                </button>
                @if (! $this->isReview() && $dueCount > 0)
                    <p class="mt-3 text-sm">
                        <a href="{{ route('gyms.play', $gym->slug) }}?mode=review" class="text-primary hover:underline">
                            🔁 {{ $dueCount }} {{ Str::plural('card', $dueCount) }} due for review — do a retention pass instead
                        </a>
                    </p>
                @endif
            @endif

            {{-- Red Queen Knowledge Ladder — what this gym climbs toward. --}}
            <div class="mt-6 border-t border-border pt-5 text-left">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted">
                        Knowledge ladder — trains up to
                        <span class="font-semibold text-primary">{{ $gymCeiling }} · {{ $ladder[$gymCeiling]['name'] }}</span>
                    </p>
                    <a href="{{ route('wiki.show', 'red-queen-knowledge-ladder') }}" class="shrink-0 text-xs text-primary hover:underline">the ladder →</a>
                </div>
                <x-knowledge-ladder :ladder="$ladder" :ceiling="$gymCeiling" />
            </div>
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
            <div class="flex items-center justify-between text-xs text-muted mb-2">
                <span>Round {{ $round }} / {{ $rounds }}</span>
                <span x-text="remaining.toFixed(1) + 's'"></span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-surface-sunken mb-5 overflow-hidden">
                <div class="h-1.5 rounded-full bg-primary transition-none"
                     :style="`width: ${(remaining / total) * 100}%`"></div>
            </div>

            <div class="rounded-xl border border-border bg-surface p-6">
                <p class="text-xs uppercase tracking-wide text-subtle mb-2">Which pattern?</p>
                <p class="text-lg text-fg">{{ $item->prompt }}</p>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($item->choices as $choice)
                    <button type="button" @click="pick(@js($choice))" data-choice="{{ $choice }}"
                            class="rounded-lg border border-border bg-surface px-4 py-3 text-left text-fg hover:border-primary hover:bg-surface-sunken">
                        {{ $choice }}
                    </button>
                @endforeach
            </div>
        </div>

    {{-- FEEDBACK ------------------------------------------------------------}}
    @elseif ($phase === 'feedback' && $feedback && $item)
        <div class="flex items-center justify-between text-xs text-muted mb-2">
            <span>Round {{ $round }} / {{ $rounds }}</span>
        </div>

        @php($ok = $feedback['correct'])
        <div class="rounded-xl border p-6 {{ $ok ? 'border-success bg-success-subtle/50' : 'border-danger bg-danger-subtle/50' }}">
            <div class="text-sm font-semibold {{ $ok ? 'text-success-fg' : 'text-danger-fg' }}">
                @if ($ok) ✓ Correct
                @elseif (is_null($feedback['selected'])) ⏱ Time up — it was {{ $feedback['answer'] }}
                @else ✗ Not quite — you chose {{ $feedback['selected'] }}
                @endif
            </div>
            <p class="mt-1 text-fg font-medium">{{ $item->prompt }}</p>
            <p class="mt-2 text-sm"><span class="text-muted">Answer:</span> <span class="font-semibold text-fg">{{ $feedback['answer'] }}</span></p>
            @if ($feedback['explanation'])
                <p class="mt-2 text-sm text-muted">{{ $feedback['explanation'] }}</p>
            @endif
            @if ($feedback['detail'])
                <p class="mt-2 text-sm text-muted border-l-2 border-border pl-3">{{ $feedback['detail'] }}</p>
            @endif
        </div>

        <button wire:click="next"
                class="mt-4 w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-primary-fg hover:bg-primary-hover">
            {{ $round >= $rounds ? 'See summary' : 'Next round' }}
        </button>

    {{-- SUMMARY -------------------------------------------------------------}}
    @elseif ($phase === 'summary' && $summary)
        @php($s = $summary['session'])
        <div class="rounded-xl border border-border bg-surface p-6 text-center">
            <h1 class="font-serif text-2xl font-semibold tracking-tight text-fg">
                @if ($summary['blindSpots'])
                    Session complete — blind spot found.
                @else
                    {{ $summary['passed'] ? 'Session complete — reflex is stabilizing.' : 'Session complete — keep drilling.' }}
                @endif
            </h1>

            <div class="mt-5 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-surface-sunken p-3">
                    <div class="text-2xl font-bold {{ $summary['passed'] ? 'text-success' : 'text-fg' }}">{{ round($s->accuracy * 100) }}%</div>
                    <div class="text-xs text-muted">{{ $s->correct }}/{{ $s->total }} correct</div>
                </div>
                <div class="rounded-lg bg-surface-sunken p-3">
                    <div class="text-2xl font-bold text-fg">{{ $s->median_latency_ms ? number_format($s->median_latency_ms / 1000, 1) : '—' }}s</div>
                    <div class="text-xs text-muted">median response</div>
                </div>
                <div class="rounded-lg bg-surface-sunken p-3">
                    <div class="text-2xl font-bold text-primary">L{{ $summary['level'] }}</div>
                    <div class="text-xs text-muted">{{ $summary['rung']['name'] }}</div>
                </div>
            </div>

            {{-- Blind-spot floor: a category at 0-for-n caps the rung. --}}
            @if ($summary['blindSpots'])
                <div class="mt-5 rounded-lg border border-warning bg-warning-subtle/60 p-4 text-left">
                    <p class="text-sm font-semibold text-warning-fg">
                        {{ Str::plural('Blind spot', count($summary['blindSpots'])) }} — nothing correct in
                        {{ count($summary['blindSpots']) === 1 ? 'this category' : 'these '.count($summary['blindSpots']).' categories' }}
                    </p>
                    <ul class="mt-2 space-y-1 text-sm text-fg">
                        @foreach ($summary['blindSpots'] as $spot)
                            <li>
                                <span class="font-medium">{{ $spot['category'] }}</span>
                                <span class="text-muted">— 0 of {{ $spot['items'] }} {{ Str::plural('item', $spot['items']) }} in this run</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-sm text-muted">
                        @if ($summary['withheld'])
                            Accuracy and speed alone read
                            <span class="font-medium text-fg">L{{ $summary['withheld']['level'] }} · {{ $summary['withheld']['name'] }}</span>,
                            withheld to <span class="font-medium text-fg">L{{ $summary['level'] }}</span>:
                            an average across categories hides one you cannot recognize at all.
                        @else
                            An average across categories hides one you cannot recognize at all.
                        @endif
                        Drill {{ count($summary['blindSpots']) === 1 ? 'it' : 'them' }} before taking on new material.
                    </p>
                    @if ($gym->course)
                        <a href="{{ route('courses.show', $gym->course->slug) }}"
                           class="mt-3 inline-block text-sm text-primary hover:underline">Find the lesson in {{ $gym->course->title }} &rarr;</a>
                    @endif
                </div>
            @endif

            {{-- Red Queen Knowledge Ladder read for this session. --}}
            <div class="mt-5 text-left">
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <p class="text-sm text-fg">
                        <span class="font-semibold">Level {{ $summary['level'] }} · {{ $summary['rung']['name'] }}</span>
                        <span class="text-muted">— {{ $summary['rung']['standard'] }}</span>
                    </p>
                    <a href="{{ route('wiki.show', 'red-queen-knowledge-ladder') }}" class="shrink-0 text-xs text-primary hover:underline">ladder →</a>
                </div>
                <x-knowledge-ladder :ladder="$ladder" :current="$summary['level']" :ceiling="$gymCeiling" />
                @if ($summary['nextRung'])
                    <p class="mt-2 text-xs text-muted">
                        Next rung: <span class="font-medium text-fg">Level {{ $summary['nextRung']['level'] }} · {{ $summary['nextRung']['name'] }}</span> — {{ $summary['nextRung']['standard'] }}
                    </p>
                @else
                    <p class="mt-2 text-xs text-muted">Top rung this gym trains — deeper mastery (transfer, teaching) lives beyond timed drills.</p>
                @endif
            </div>

            @if ($summary['confusion'])
                <p class="mt-4 text-sm text-muted">
                    Most confused: <span class="font-medium text-danger-fg">{{ $summary['confusion']['pair'] }}</span>
                    <span class="text-subtle">(×{{ $summary['confusion']['count'] }})</span>
                </p>
            @endif

            <div class="mt-6 flex items-center justify-center gap-3">
                <button wire:click="start" class="rounded-md bg-primary px-5 py-2 text-sm font-semibold text-primary-fg hover:bg-primary-hover">Play again</button>
                @if ($gym->course)
                    <a href="{{ route('courses.show', $gym->course->slug) }}" class="rounded-md border border-border px-5 py-2 text-sm text-fg hover:bg-surface-sunken">Back to course</a>
                @endif
            </div>
        </div>
    @endif
</div>
