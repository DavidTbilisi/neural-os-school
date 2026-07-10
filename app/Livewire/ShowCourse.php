<?php

namespace App\Livewire;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class ShowCourse extends Component
{
    public Course $course;

    public ?Enrollment $enrollment = null;

    /** @var array<int, bool> lesson_id => completed */
    public array $completed = [];

    public function mount(string $slug): void
    {
        $course = Course::where('slug', $slug)
            ->with(['domain', 'sourcePage', 'modules.lessons.page', 'prerequisites', 'gyms'])
            ->firstOrFail();

        // Published is public; draft is a staff-only preview.
        abort_unless($course->isPublished() || (auth()->user()?->isStaff() ?? false), 404);

        $this->course = $course;
        $this->refreshProgress();
    }

    public function enroll(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'));

            return;
        }

        $this->enrollment = Enrollment::firstOrCreate(
            ['user_id' => auth()->id(), 'course_id' => $this->course->id],
            ['enrolled_at' => Carbon::now()],
        );

        $this->refreshProgress();
    }

    public function toggleLesson(int $lessonId): void
    {
        if (! $this->enrollment) {
            return; // must enroll first
        }

        // Only lessons that belong to this course can be toggled.
        $lesson = $this->course->allLessons()->firstWhere('id', $lessonId);
        if (! $lesson) {
            return;
        }

        $existing = LessonCompletion::where('user_id', auth()->id())
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            LessonCompletion::create([
                'user_id' => auth()->id(),
                'lesson_id' => $lesson->id,
                'completed_at' => Carbon::now(),
            ]);
        }

        $this->syncCompletion();
        $this->refreshProgress();
    }

    /** Flip the enrollment's completed_at based on required-lesson progress. */
    private function syncCompletion(): void
    {
        if (! $this->enrollment) {
            return;
        }

        $done = $this->course->progressFor(auth()->user()) >= 1.0
            && $this->course->requiredLessons()->isNotEmpty();

        $this->enrollment->completed_at = $done ? ($this->enrollment->completed_at ?? Carbon::now()) : null;
        $this->enrollment->save();
    }

    private function refreshProgress(): void
    {
        $user = auth()->user();

        $this->enrollment = $user
            ? Enrollment::where('user_id', $user->id)->where('course_id', $this->course->id)->first()
            : null;

        $this->completed = $user
            ? LessonCompletion::where('user_id', $user->id)
                ->whereIn('lesson_id', $this->course->allLessons()->pluck('id'))
                ->pluck('lesson_id')
                ->flip()
                ->map(fn () => true)
                ->all()
            : [];
    }

    public function render()
    {
        $user = auth()->user();
        $required = $this->course->requiredLessons();
        $doneCount = collect($this->completed)->keys()
            ->intersect($required->pluck('id'))->count();

        $prereqs = $this->course->prerequisites->map(fn (Course $p) => [
            'course' => $p,
            'met' => $user
                ? Enrollment::where('user_id', $user->id)->where('course_id', $p->id)->whereNotNull('completed_at')->exists()
                : false,
        ]);

        return view('livewire.show-course', [
            'progress' => $required->isNotEmpty() ? $doneCount / $required->count() : 0.0,
            'requiredTotal' => $required->count(),
            'requiredDone' => $doneCount,
            'prereqs' => $prereqs,
            'prereqsMet' => $this->course->prerequisitesMetBy($user),
            'practiceGym' => $this->course->gyms->firstWhere('status', \App\Models\Gym::STATUS_PUBLISHED),
        ]);
    }
}
