<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight">Courses</h1>
        <p class="text-sm text-gray-500 mt-1">Guided paths through the wiki — {{ $cards->count() }} published.</p>
    </div>

    @if ($cards->isEmpty())
        <div class="rounded-lg border border-gray-100 bg-white px-4 py-10 text-center text-gray-500">
            No courses published yet.
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($cards as $card)
                @php($course = $card['course'])
                <a href="{{ route('courses.show', $course->slug) }}"
                   class="block rounded-lg border border-gray-100 bg-white p-4 hover:border-indigo-300 hover:shadow-sm transition">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-semibold text-indigo-700">{{ $course->title }}</h2>
                        @if ($card['enrolled'])
                            <span class="shrink-0 text-xs rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5">Enrolled</span>
                        @elseif (! $card['prereqsMet'])
                            <span class="shrink-0 text-xs rounded-full bg-amber-50 text-amber-700 px-2 py-0.5" title="Recommended prerequisites not finished">Prereqs</span>
                        @endif
                    </div>

                    @if ($course->subtitle)
                        <p class="mt-1 text-sm text-gray-600">{{ Str::limit($course->subtitle, 140) }}</p>
                    @endif

                    <div class="mt-3 flex items-center gap-2 text-xs text-gray-500">
                        @if ($course->domain)
                            <span class="rounded bg-indigo-50 text-indigo-700 px-1.5 py-0.5">{{ $course->domain->name }}</span>
                        @endif
                        <span>{{ $card['moduleCount'] }} modules</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $card['lessonCount'] }} lessons</span>
                    </div>

                    @if ($card['enrolled'] && $card['progress'] > 0)
                        <div class="mt-3">
                            <div class="h-1.5 w-full rounded-full bg-gray-100">
                                <div class="h-1.5 rounded-full bg-emerald-500" style="width: {{ round($card['progress'] * 100) }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ round($card['progress'] * 100) }}% complete</p>
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
