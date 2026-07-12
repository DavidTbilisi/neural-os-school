{{--
    Embedded "check your understanding" — self-paced (no timer/countdown),
    plain correct/incorrect feedback with no score/streak/badge chrome. Reps
    answered here feed the same coverage evidence as a full gym drill (see
    GymScoring) via this lesson's tagged GymItems.
--}}
<div class="rounded-xl border border-border bg-surface p-6" wire:key="lesson-check-{{ $lesson->id }}">
    <h2 class="font-serif text-lg font-semibold text-fg mb-3">Check your understanding</h2>

    {{-- INTRO ---------------------------------------------------------------}}
    @if ($phase === 'intro')
        @auth
            <p class="text-sm text-muted mb-4">{{ count($order) }} {{ Str::plural('question', count($order)) }} — self-paced, no timer.</p>
            <button wire:click="start"
                    class="rounded-md bg-primary px-5 py-2 text-sm font-semibold text-primary-fg hover:bg-primary-hover">
                Start check
            </button>
        @else
            <p class="text-sm text-muted">
                <a href="{{ route('login') }}" class="text-primary hover:underline">Log in</a> to try this check.
            </p>
        @endauth

    {{-- PROMPT (untimed) -------------------------------------------------------}}
    @elseif ($phase === 'prompt' && $item)
        <div wire:key="lesson-check-round-{{ $index }}"
             x-data="{ t0: 0, init() { this.t0 = performance.now(); }, pick(choice) { $wire.answer(choice, Math.round(performance.now() - this.t0)); } }">
            <p class="text-xs text-muted mb-2">Question {{ $round }} / {{ $rounds }}</p>
            <p class="text-fg">{{ $item->prompt }}</p>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($item->choices as $choice)
                    <button type="button" @click="pick(@js($choice))"
                            class="rounded-lg border border-border bg-surface px-4 py-3 text-left text-fg hover:border-primary hover:bg-surface-sunken">
                        {{ $choice }}
                    </button>
                @endforeach
            </div>
        </div>

    {{-- FEEDBACK ---------------------------------------------------------------}}
    @elseif ($phase === 'feedback' && $feedback && $item)
        <p class="text-xs text-muted mb-2">Question {{ $round }} / {{ $rounds }}</p>

        @php($ok = $feedback['correct'])
        <div class="rounded-lg border p-4 {{ $ok ? 'border-success bg-success-subtle/50' : 'border-danger bg-danger-subtle/50' }}">
            <div class="text-sm font-semibold {{ $ok ? 'text-success-fg' : 'text-danger-fg' }}">
                {{ $ok ? '✓ Correct' : '✗ Not quite — you chose '.$feedback['selected'] }}
            </div>
            <p class="mt-2 text-sm"><span class="text-muted">Answer:</span> <span class="font-semibold text-fg">{{ $feedback['answer'] }}</span></p>
            @if ($feedback['explanation'])
                <p class="mt-2 text-sm text-muted">{{ $feedback['explanation'] }}</p>
            @endif
            @if ($feedback['detail'])
                <p class="mt-2 text-sm text-muted border-l-2 border-border pl-3">{{ $feedback['detail'] }}</p>
            @endif
        </div>

        <button wire:click="next"
                class="mt-4 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-fg hover:bg-primary-hover">
            {{ $round >= $rounds ? 'Done' : 'Next question' }}
        </button>

    {{-- DONE ---------------------------------------------------------------}}
    @elseif ($phase === 'done' && $session)
        <p class="text-sm text-fg">Check complete — {{ $session->correct }}/{{ $session->total }} correct.</p>
        <p class="mt-1 text-xs text-muted">This counts toward the module's coverage evidence, alongside gym practice.</p>
    @endif
</div>
