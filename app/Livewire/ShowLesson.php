<?php

namespace App\Livewire;

use App\Livewire\Concerns\ReadsWikiPage;
use App\Livewire\Concerns\TracksCourseProgress;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GymItem;
use App\Models\Lesson;
use App\Models\Page;
use App\Services\Wiki\RepresentationBuilder;
use App\Services\Wiki\WikiRenderer;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The in-course lesson reader: reads a lesson's page through the same
 * WikiRenderer pipeline as the standalone reader (via ReadsWikiPage), inside
 * the course shell — sidebar, prev/next, and an inline mark-complete control
 * (via TracksCourseProgress) — so reading a lesson no longer means leaving
 * the course.
 */
#[Layout('layouts.public')]
class ShowLesson extends Component
{
    use ReadsWikiPage, TracksCourseProgress;

    public Course $course;

    public Lesson $lesson;

    public Page $page;

    public string $html;

    /** @var \Illuminate\Support\Collection<int,\App\Models\Page> */
    public $backlinks;

    public ?Enrollment $enrollment = null;

    /** @var array<int, bool> lesson_id => completed */
    public array $completed = [];

    public ?Lesson $prevLesson = null;

    public ?Lesson $nextLesson = null;

    public function mount(string $courseSlug, string $lessonSlug, WikiRenderer $renderer): void
    {
        $courseModel = Course::where('slug', $courseSlug)
            ->with(['domain', 'modules.lessons.page', 'modules.gymItems'])
            ->firstOrFail();

        // Published is public; draft is a staff-only preview — same rule as ShowCourse.
        abort_unless($courseModel->isPublished() || (auth()->user()?->isStaff() ?? false), 404);
        $this->course = $courseModel;

        $lessonModel = $courseModel->allLessons()->first(fn (Lesson $l) => $l->page?->slug === $lessonSlug);
        abort_if(! $lessonModel || ! $lessonModel->page, 404);
        $this->lesson = $lessonModel;

        $this->loadPage($lessonModel->page, $renderer);
        $this->computeNeighbors();
        $this->refreshProgress();
        // Coverage can be earned via the embedded lesson check (no lesson
        // toggle to hook), so the gate is re-checked on every visit here too
        // — same reasoning as ShowCourse::mount().
        $this->syncCompletion();
    }

    /**
     * Prev/next over the course's full authored reading order (allLessons()),
     * restricted to lessons whose page is actually viewable — an unpublished
     * placeholder lesson has nothing to navigate to, so it's skipped rather
     * than becoming a dead "next" link. Optional lessons ARE included: prev/
     * next follows the authored reading sequence, not the required-for-
     * completion subset (the "optional" chip already communicates skippable).
     */
    private function computeNeighbors(): void
    {
        $staff = auth()->user()?->isStaff() ?? false;

        $navigable = $this->course->allLessons()->filter(
            fn (Lesson $l) => $l->page && ($l->page->isPublished() || $l->page->visibility === Page::VISIBILITY_UNLISTED || $staff)
        )->values();

        $index = $navigable->search(fn (Lesson $l) => $l->id === $this->lesson->id);

        $this->prevLesson = $index !== false ? $navigable->get($index - 1) : null;
        $this->nextLesson = $index !== false ? $navigable->get($index + 1) : null;
    }

    public function hasCheck(): bool
    {
        return GymItem::where('lesson_id', $this->lesson->id)->exists();
    }

    public function render(RepresentationBuilder $representations)
    {
        return view('livewire.show-lesson', [
            'reps' => $representations->for($this->page),
            'lessonUrl' => fn (Lesson $l) => route('courses.lessons.show', [$this->course->slug, $l->page->slug]),
        ]);
    }
}
