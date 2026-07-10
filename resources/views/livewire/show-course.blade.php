<div>
    @if (! $course->isPublished())
        <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
            Draft course — staff preview. Not visible to learners until published.
        </div>
    @endif

    <div class="mb-2">
        <a href="{{ route('courses') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; All courses</a>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ $course->title }}</h1>
            @if ($course->subtitle)
                <p class="mt-1 text-gray-600">{{ $course->subtitle }}</p>
            @endif
            <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                @if ($course->domain)
                    <span class="rounded bg-indigo-50 text-indigo-700 px-1.5 py-0.5">{{ $course->domain->name }}</span>
                @endif
                <span>{{ $course->modules->count() }} modules · {{ $requiredTotal }} required lessons</span>
            </div>
        </div>

        <div class="shrink-0">
            @if ($enrollment)
                <div class="text-right">
                    <div class="text-sm font-medium text-emerald-700">
                        {{ $enrollment->isComplete() ? '✓ Completed' : 'Enrolled' }}
                    </div>
                    <div class="mt-1 h-2 w-40 rounded-full bg-gray-100">
                        <div class="h-2 rounded-full bg-emerald-500" style="width: {{ round($progress * 100) }}%"></div>
                    </div>
                    <div class="mt-1 text-xs text-gray-500">{{ $requiredDone }}/{{ $requiredTotal }} · {{ round($progress * 100) }}%</div>
                </div>
            @else
                <button wire:click="enroll"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    {{ auth()->check() ? 'Enroll' : 'Log in to enroll' }}
                </button>
            @endif
        </div>
    </div>

    @if ($prereqs->isNotEmpty())
        <div class="mt-5 rounded-lg border {{ $prereqsMet ? 'border-gray-100' : 'border-amber-200 bg-amber-50/40' }} p-4">
            <h2 class="text-sm font-semibold text-gray-700">Recommended first</h2>
            <p class="text-xs text-gray-500 mb-2">Prerequisites are a guide — you can still enroll now.</p>
            <ul class="space-y-1 text-sm">
                @foreach ($prereqs as $p)
                    <li class="flex items-center gap-2">
                        <span class="{{ $p['met'] ? 'text-emerald-600' : 'text-gray-400' }}">{{ $p['met'] ? '✓' : '○' }}</span>
                        <a href="{{ route('courses.show', $p['course']->slug) }}" class="text-indigo-700 hover:underline">{{ $p['course']->title }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($course->description)
        <div class="prose prose-sm max-w-none mt-5">{!! Str::markdown($course->description) !!}</div>
    @endif

    <div class="mt-6 space-y-5">
        @foreach ($course->modules as $mi => $module)
            <section class="rounded-lg border border-gray-100 bg-white">
                <header class="border-b border-gray-100 px-4 py-3">
                    <h3 class="font-semibold text-gray-900">
                        <span class="text-gray-400">{{ $mi + 1 }}.</span> {{ $module->title }}
                    </h3>
                    @if ($module->summary)
                        <p class="text-sm text-gray-500 mt-0.5">{{ $module->summary }}</p>
                    @endif
                </header>
                <ul class="divide-y divide-gray-50">
                    @forelse ($module->lessons as $lesson)
                        @php($isDone = $completed[$lesson->id] ?? false)
                        @php($viewable = $lesson->page && ($lesson->page->isPublished() || $lesson->page->visibility === \App\Models\Page::VISIBILITY_UNLISTED || (auth()->user()?->isStaff() ?? false)))
                        <li class="flex items-center gap-3 px-4 py-2.5">
                            @if ($enrollment)
                                <button wire:click="toggleLesson({{ $lesson->id }})"
                                        aria-label="Toggle complete"
                                        class="shrink-0 h-5 w-5 rounded border flex items-center justify-center text-xs
                                               {{ $isDone ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-gray-300 text-transparent hover:border-emerald-400' }}">
                                    ✓
                                </button>
                            @else
                                <span class="shrink-0 h-5 w-5 rounded border border-gray-200 bg-gray-50"></span>
                            @endif

                            <span class="flex-1 {{ $isDone ? 'text-gray-400 line-through' : 'text-gray-800' }}">
                                @if ($viewable)
                                    <a href="{{ route('wiki.show', $lesson->page->slug) }}" class="text-indigo-700 hover:underline">{{ $lesson->title }}</a>
                                @else
                                    {{ $lesson->title }}
                                    <span class="ml-1 text-xs text-gray-400">(not yet published)</span>
                                @endif
                            </span>

                            @if ($lesson->optional)
                                <span class="shrink-0 text-xs rounded bg-gray-100 text-gray-500 px-1.5 py-0.5">optional</span>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-gray-400">No lessons in this module yet.</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
</div>
