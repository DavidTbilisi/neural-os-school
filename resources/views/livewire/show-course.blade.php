<div>
    @if (! $course->isPublished())
        <div class="mb-4 rounded-md bg-warning-subtle border border-warning px-3 py-2 text-sm text-warning-fg">
            Draft course — staff preview. Not visible to learners until published.
        </div>
    @endif

    <div class="mb-2 flex items-center justify-between">
        <a href="{{ route('courses') }}" class="text-sm text-muted hover:text-fg">&larr; All courses</a>
        <div class="flex items-center gap-2">
            @if ($practiceGym)
                <a href="{{ route('gyms.play', $practiceGym->slug) }}"
                   class="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-sm font-medium text-primary hover:bg-surface-sunken">
                    🏋 Practice
                </a>
            @endif
            @auth
                <a href="{{ route('courses.sketchpad', $course->slug) }}"
                   class="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-sm font-medium text-primary hover:bg-surface-sunken">
                    ✎ Sketchpad
                </a>
            @endauth
        </div>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="font-serif text-3xl font-semibold tracking-tight text-fg">{{ $course->title }}</h1>
            @if ($course->subtitle)
                <p class="mt-1 text-muted">{{ $course->subtitle }}</p>
            @endif
            <div class="mt-2 flex items-center gap-2 text-xs text-muted">
                @if ($course->domain)
                    <span class="rounded bg-primary-subtle text-primary-subtle-fg px-1.5 py-0.5">{{ $course->domain->name }}</span>
                @endif
                <span>{{ $course->modules->count() }} modules · {{ $requiredTotal }} required lessons</span>
            </div>
        </div>

        <div class="shrink-0">
            @if ($enrollment)
                <div class="text-right">
                    <div class="text-sm font-medium {{ empty($pendingEvidence) ? 'text-success-fg' : 'text-warning-fg' }}">
                        {{ $enrollment->isComplete() ? '✓ Completed' : (empty($pendingEvidence) ? 'Enrolled' : 'Evidence pending') }}
                    </div>
                    <div class="mt-1 h-2 w-40 rounded-full bg-surface-sunken">
                        <div class="h-2 rounded-full bg-success" style="width: {{ round($progress * 100) }}%"></div>
                    </div>
                    <div class="mt-1 text-xs text-muted">{{ $requiredDone }}/{{ $requiredTotal }} · {{ round($progress * 100) }}%</div>
                </div>
            @else
                <button wire:click="enroll"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-fg hover:bg-primary-hover">
                    {{ auth()->check() ? 'Enroll' : 'Log in to enroll' }}
                </button>
            @endif
        </div>
    </div>

    @if (! empty($pendingEvidence))
        <div class="mt-5 rounded-lg border border-warning bg-warning-subtle/40 p-4 text-sm text-fg">
            <span class="font-semibold">All lessons read ✓</span> — completing this course now needs drill
            evidence for <span class="font-medium">{{ implode(', ', $pendingEvidence) }}</span>.
            The checkbox says you read it; the gym proves you know it.
            @if ($practiceGym)
                <a href="{{ route('gyms.play', $practiceGym->slug) }}" class="text-primary hover:underline">🏋 Practice now</a>
            @endif
        </div>
    @endif

    @if ($prereqs->isNotEmpty())
        <div class="mt-5 rounded-lg border {{ $prereqsMet ? 'border-border' : 'border-warning bg-warning-subtle/40' }} p-4">
            <h2 class="text-sm font-semibold text-fg">Recommended first</h2>
            <p class="text-xs text-muted mb-2">Prerequisites are a guide — you can still enroll now.</p>
            <ul class="space-y-1 text-sm">
                @foreach ($prereqs as $p)
                    <li class="flex items-center gap-2">
                        <span class="{{ $p['met'] ? 'text-success' : 'text-subtle' }}">{{ $p['met'] ? '✓' : '○' }}</span>
                        <a href="{{ route('courses.show', $p['course']->slug) }}" class="text-primary hover:underline">{{ $p['course']->title }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($course->description)
        <div class="prose prose-sm max-w-none mt-5
                    prose-a:text-primary
                    [--tw-prose-body:rgb(var(--color-fg-muted))]
                    [--tw-prose-headings:rgb(var(--color-fg))]
                    [--tw-prose-links:rgb(var(--color-fg-link))]
                    [--tw-prose-bold:rgb(var(--color-fg))]">{!! Str::markdown($course->description) !!}</div>
    @endif

    <div class="mt-6 space-y-5">
        @foreach ($course->modules as $mi => $module)
            <section class="rounded-lg border border-border bg-surface">
                <header class="border-b border-border px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-serif font-semibold text-fg">
                            <span class="text-subtle">{{ $mi + 1 }}.</span> {{ $module->title }}
                        </h3>
                        @php($ev = $moduleEvidence[$module->id] ?? null)
                        @if ($ev)
                            @if ($ev['covered'])
                                <span class="shrink-0 text-xs rounded-full border border-success text-success-fg px-2 py-0.5">✓ Covered</span>
                            @elseif ($ev['insufficient'])
                                <span class="shrink-0 text-xs rounded-full bg-surface-sunken text-muted px-2 py-0.5" title="Coverage needs ≥{{ \App\Services\Meter\Report::MIN_SIGNAL }} drill reps">🏋 {{ $ev['n'] }}/{{ \App\Services\Meter\Report::MIN_SIGNAL }} reps</span>
                            @elseif (! $ev['sustained'])
                                <span class="shrink-0 text-xs rounded-full bg-surface-sunken text-muted px-2 py-0.5" title="Coverage must hold across ≥{{ \App\Services\Meter\Report::MIN_SESSIONS }} sessions">🏋 one more session</span>
                            @else
                                <span class="shrink-0 text-xs rounded-full bg-warning-subtle text-warning-fg px-2 py-0.5" title="Rolling accuracy vs the gym's pass bar">🏋 {{ round($ev['accuracy'] * 100) }}% — pass is {{ round($ev['pass'] * 100) }}%</span>
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
                        @php($viewable = $lesson->page && ($lesson->page->isPublished() || $lesson->page->visibility === \App\Models\Page::VISIBILITY_UNLISTED || (auth()->user()?->isStaff() ?? false)))
                        <li class="flex items-center gap-3 px-4 py-2.5">
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

                            <span class="flex-1 {{ $isDone ? 'text-subtle line-through' : 'text-fg' }}">
                                @if ($viewable)
                                    <a href="{{ route('wiki.show', $lesson->page->slug) }}" class="text-primary hover:underline">{{ $lesson->title }}</a>
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
</div>
