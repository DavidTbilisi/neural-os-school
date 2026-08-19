{{--
    The module/lesson list: per-module evidence chip + per-lesson mark-complete
    toggle + link. Shared by the course overview (show-course.blade.php) and
    the in-course lesson sidebar (show-lesson.blade.php) so both surfaces
    render identical lesson-list markup.

    Expects: $course, $completed, $enrollment, $moduleEvidence, $lessonUrl
    (closure: Lesson -> string). Optional: $currentLessonId (highlights the
    active lesson row when set, for the in-course sidebar).
--}}
@php($currentLessonId = $currentLessonId ?? null)

<div class="mt-6 space-y-5">
    @foreach ($course->modules as $mi => $module)
        <section class="rounded-lg border border-border bg-surface">
            <header class="border-b border-border px-4 py-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-display font-bold text-fg">
                        <span class="text-subtle">{{ $mi + 1 }}.</span> {{ $module->title }}
                    </h3>
                    @php($ev = $moduleEvidence[$module->id] ?? null)
                    @if ($ev)
                        @if ($ev['covered'])
                            <span class="shrink-0 text-xs rounded-full border border-success text-success-fg px-2 py-0.5" title="Target L{{ $ev['targetRung']['level'] }} {{ $ev['targetRung']['name'] }}: {{ $ev['targetRung']['standard'] }}">✓ Covered · L{{ $ev['targetRung']['level'] }}</span>
                        @elseif (! $ev['certifiable'])
                            <span class="shrink-0 text-xs rounded-full bg-surface-sunken text-muted px-2 py-0.5" title="L{{ $ev['targetRung']['level'] }} {{ $ev['targetRung']['name'] }} ({{ $ev['targetRung']['standard'] }}) is beyond a timed recognition drill">target L{{ $ev['targetRung']['level'] }} — needs a deeper instrument</span>
                        @elseif ($ev['insufficient'])
                            <span class="shrink-0 text-xs rounded-full bg-surface-sunken text-muted px-2 py-0.5" title="Coverage needs ≥{{ \App\Services\Meter\Report::MIN_SIGNAL }} drill reps">🏋 {{ $ev['n'] }}/{{ \App\Services\Meter\Report::MIN_SIGNAL }} reps</span>
                        @elseif (! $ev['sustained'])
                            <span class="shrink-0 text-xs rounded-full bg-surface-sunken text-muted px-2 py-0.5" title="Coverage must hold across ≥{{ \App\Services\Meter\Report::MIN_SESSIONS }} sessions">🏋 one more session</span>
                        @else
                            <span class="shrink-0 text-xs rounded-full bg-warning-subtle text-warning-fg px-2 py-0.5" title="{{ round($ev['accuracy'] * 100) }}% accuracy · median {{ $ev['medianLatencyMs'] ? number_format($ev['medianLatencyMs'] / 1000, 1).'s' : '—' }} · target: {{ $ev['targetRung']['standard'] }}">🏋 L{{ $ev['rung']['level'] }} — target is L{{ $ev['targetRung']['level'] }} {{ $ev['targetRung']['name'] }}</span>
                        @endif
                    @endif
                </div>
                @if ($module->summary)
                    <p class="text-sm text-muted mt-0.5">{{ $module->summary }}</p>
                @endif
            </header>
            <ul class="divide-y divide-border-subtle">
                @forelse ($module->lessons as $lesson)
                    @php($isDone = $completed[$lesson->id] ?? false)
                    @php($isCurrent = $lesson->id === $currentLessonId)
                    @php($viewable = $lesson->page && ($lesson->page->isPublished() || $lesson->page->visibility === \App\Models\Page::VISIBILITY_UNLISTED || (auth()->user()?->isStaff() ?? false)))
                    <li class="flex items-center gap-3 px-4 py-2.5 {{ $isCurrent ? 'bg-primary-subtle/40' : '' }}">
                        @if ($enrollment)
                            <button wire:click="toggleLesson({{ $lesson->id }})"
                                    aria-label="Toggle complete"
                                    class="shrink-0 h-5 w-5 rounded border flex items-center justify-center text-xs
                                           {{ $isDone ? 'bg-success border-success text-white' : 'border-border-strong text-transparent hover:border-success' }}">
                                ✓
                            </button>
                        @else
                            <span class="shrink-0 h-5 w-5 rounded border border-border bg-surface-sunken"></span>
                        @endif

                        <span class="flex-1 {{ $isDone ? 'text-subtle line-through' : 'text-fg' }} {{ $isCurrent ? 'font-semibold' : '' }}">
                            @if ($viewable)
                                <a href="{{ $lessonUrl($lesson) }}" class="{{ $isCurrent ? 'text-fg' : 'text-primary hover:underline' }}">{{ $lesson->title }}</a>
                            @else
                                {{ $lesson->title }}
                                <span class="ml-1 text-xs text-subtle">(not yet published)</span>
                            @endif
                        </span>

                        @if ($lesson->optional)
                            <span class="shrink-0 text-xs rounded bg-surface-sunken text-muted px-1.5 py-0.5">optional</span>
                        @endif
                    </li>
                @empty
                    <li class="px-4 py-3 text-sm text-subtle">No lessons in this module yet.</li>
                @endforelse
            </ul>
        </section>
    @endforeach
</div>
