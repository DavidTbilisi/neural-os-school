<div>
    <div class="mb-6">
        <h1 class="font-display text-3xl font-bold tracking-tight text-fg">Courses</h1>
        <p class="text-sm text-muted mt-1">Guided paths through the wiki — {{ $cards->count() }} published.</p>
    </div>

    @if ($cards->isEmpty())
        <div class="rounded-lg border border-border bg-surface px-4 py-10 text-center text-muted">
            No courses published yet.
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($cards as $card)
                @php($course = $card['course'])
                {{-- The tinted head is keyed to the course ID, so a course keeps
                     its color no matter how the grid is sorted. --}}
                @php($hue = \App\Support\Palette::nth($course->id))
                <a href="{{ route('courses.show', $course->slug) }}"
                   class="block overflow-hidden rounded-lg border border-border bg-surface transition hover:shadow-md">
                    <div class="{{ $hue['fill'] }} px-4 pb-4 pt-4">
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="font-display text-lg font-bold {{ $hue['text'] }}">{{ $course->title }}</h2>
                            @if ($card['enrolled'])
                                <span class="shrink-0 rounded-full bg-surface/70 px-2.5 py-1 text-xs font-semibold {{ $hue['text'] }}">Enrolled</span>
                            @elseif (! $card['prereqsMet'])
                                <span class="shrink-0 rounded-full bg-warning-subtle px-2.5 py-1 text-xs text-warning-fg" title="Recommended prerequisites not finished">Prereqs</span>
                            @endif
                        </div>

                        @if ($course->subtitle)
                            <p class="mt-1 text-sm opacity-80 {{ $hue['text'] }}">{{ Str::limit($course->subtitle, 140) }}</p>
                        @endif
                    </div>

                    <div class="px-4 pb-4 pt-3">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
                        @if ($course->domain)
                            <span class="whitespace-nowrap rounded-full px-2.5 py-1 {{ \App\Support\Palette::chip($course->domain) }}">{{ $course->domain->name }}</span>
                        @endif
                        <span>{{ $card['moduleCount'] }} modules</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $card['lessonCount'] }} lessons</span>
                    </div>

                    @if ($card['enrolled'] && $card['progress'] > 0)
                        <div class="mt-3">
                            <div class="h-1.5 w-full rounded-full bg-surface-sunken">
                                <div class="h-1.5 rounded-full bg-success" style="width: {{ round($card['progress'] * 100) }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-muted">{{ round($card['progress'] * 100) }}% complete</p>
                        </div>
                    @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
