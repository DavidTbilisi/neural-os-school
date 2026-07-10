<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight">My courses</h1>
        <p class="text-sm text-gray-500 mt-1">Courses you've enrolled in.</p>
    </div>

    @if ($rows->isEmpty())
        <div class="rounded-lg border border-gray-100 bg-white px-4 py-10 text-center text-gray-500">
            You haven't enrolled in any courses yet.
            <a href="{{ route('courses') }}" class="text-indigo-700 hover:underline">Browse courses →</a>
        </div>
    @else
        <ul class="space-y-3">
            @foreach ($rows as $row)
                @php($course = $row['course'])
                <li class="rounded-lg border border-gray-100 bg-white p-4">
                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ route('courses.show', $course->slug) }}" class="font-semibold text-indigo-700 hover:underline">
                            {{ $course->title }}
                        </a>
                        @if ($row['enrollment']->isComplete())
                            <span class="text-xs rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5">✓ Completed</span>
                        @endif
                    </div>
                    <div class="mt-2 h-2 w-full rounded-full bg-gray-100">
                        <div class="h-2 rounded-full bg-emerald-500" style="width: {{ round($row['progress'] * 100) }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">{{ $row['requiredDone'] }}/{{ $row['requiredTotal'] }} lessons · {{ round($row['progress'] * 100) }}%</p>
                </li>
            @endforeach
        </ul>
    @endif
</div>
