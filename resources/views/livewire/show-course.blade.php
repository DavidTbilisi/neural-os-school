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
            <h1 class="font-display text-3xl font-bold tracking-tight text-fg">{{ $course->title }}</h1>
            @if ($course->subtitle)
                <p class="mt-1 text-muted">{{ $course->subtitle }}</p>
            @endif
            <div class="mt-2 flex items-center gap-2 text-xs text-muted">
                @if ($course->domain)
                    <span class="whitespace-nowrap rounded-full bg-primary-subtle px-2.5 py-1 text-primary-subtle-fg">{{ $course->domain->name }}</span>
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
                        class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-primary-fg hover:bg-primary-hover">
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

    @include('livewire.partials.lesson-nav-list', [
        'lessonUrl' => fn ($l) => route('courses.lessons.show', [$course->slug, $l->page->slug]),
    ])
</div>
