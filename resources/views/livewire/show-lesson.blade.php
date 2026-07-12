<div>
    <nav class="text-sm mb-4">
        <a href="{{ route('courses.show', $course->slug) }}" class="text-muted hover:text-primary hover:underline">&larr; {{ $course->title }}</a>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6 items-start">
        <aside class="lg:sticky lg:top-20 order-2 lg:order-1">
            @include('livewire.partials.lesson-nav-list', ['currentLessonId' => $lesson->id])
        </aside>

        <div class="order-1 lg:order-2 min-w-0">
            @php($prevNext = true)
            <div class="mb-4 flex items-center justify-between gap-3 text-sm">
                @if ($prevLesson)
                    <a href="{{ $lessonUrl($prevLesson) }}" class="text-muted hover:text-primary hover:underline">&larr; {{ $prevLesson->title }}</a>
                @else
                    <span></span>
                @endif
                @if ($nextLesson)
                    <a href="{{ $lessonUrl($nextLesson) }}" class="text-muted hover:text-primary hover:underline">{{ $nextLesson->title }} &rarr;</a>
                @endif
            </div>

            @include('livewire.partials.page-body')

            <div class="mt-6 flex items-center justify-between rounded-lg border border-border bg-surface px-4 py-3">
                @if ($enrollment)
                    <button wire:click="toggleLesson({{ $lesson->id }})"
                            class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium
                                   {{ ($completed[$lesson->id] ?? false) ? 'bg-success/10 text-success-fg border border-success' : 'bg-primary text-primary-fg hover:bg-primary-hover' }}">
                        {{ ($completed[$lesson->id] ?? false) ? '✓ Marked complete' : 'Mark lesson complete' }}
                    </button>
                @else
                    <span class="text-sm text-muted">{{ auth()->check() ? 'Enroll in this course to track progress.' : 'Log in and enroll to track progress.' }}</span>
                @endif
            </div>

            @if ($this->hasCheck())
                <div class="mt-6">
                    <livewire:lesson-check :for-lesson="$lesson" wire:key="lesson-check-{{ $lesson->id }}" />
                </div>
            @endif

            <div class="mt-6 flex items-center justify-between gap-3 text-sm border-t border-border pt-4">
                @if ($prevLesson)
                    <a href="{{ $lessonUrl($prevLesson) }}" class="text-muted hover:text-primary hover:underline">&larr; {{ $prevLesson->title }}</a>
                @else
                    <span></span>
                @endif
                @if ($nextLesson)
                    <a href="{{ $lessonUrl($nextLesson) }}" class="text-muted hover:text-primary hover:underline">{{ $nextLesson->title }} &rarr;</a>
                @endif
            </div>
        </div>
    </div>
</div>
