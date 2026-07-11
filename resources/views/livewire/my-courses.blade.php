<div>
    <div class="mb-6">
        <h1 class="font-serif text-3xl font-semibold tracking-tight text-fg">My courses</h1>
        <p class="text-sm text-muted mt-1">Courses you've enrolled in.</p>
    </div>

    @if ($rows->isEmpty())
        <div class="rounded-lg border border-border bg-surface px-4 py-10 text-center text-muted">
            You haven't enrolled in any courses yet.
            <a href="{{ route('courses') }}" class="text-primary hover:underline">Browse courses →</a>
        </div>
    @else
        <ul class="space-y-3">
            @foreach ($rows as $row)
                @php($course = $row['course'])
                <li class="rounded-lg border border-border bg-surface p-4">
                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ route('courses.show', $course->slug) }}" class="font-serif text-lg font-semibold text-fg hover:text-primary hover:underline">
                            {{ $course->title }}
                        </a>
                        @if ($row['enrollment']->isComplete())
                            <span class="text-xs rounded-full bg-success-subtle text-success-fg px-2 py-0.5">✓ Completed</span>
                        @endif
                    </div>
                    <div class="mt-2 h-2 w-full rounded-full bg-surface-sunken">
                        <div class="h-2 rounded-full bg-success" style="width: {{ round($row['progress'] * 100) }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-muted">{{ $row['requiredDone'] }}/{{ $row['requiredTotal'] }} lessons · {{ round($row['progress'] * 100) }}%</p>
                </li>
            @endforeach
        </ul>
    @endif
</div>
