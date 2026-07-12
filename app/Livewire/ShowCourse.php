<?php

namespace App\Livewire;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Module;
use App\Services\Meter\Report;
use App\Support\Meter;
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
            ->with(['domain', 'sourcePage', 'modules.lessons.page', 'modules.gymItems', 'prerequisites', 'gyms'])
            ->firstOrFail();

        // Published is public; draft is a staff-only preview.
        abort_unless($course->isPublished() || (auth()->user()?->isStaff() ?? false), 404);

        $this->course = $course;
        $this->refreshProgress();
        // Coverage can be earned in the gym (no lesson toggle to hook), so the
        // gate is re-checked on every visit; earned completion lands here.
        $this->syncCompletion();
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
            $completion = LessonCompletion::create([
                'user_id' => auth()->id(),
                'lesson_id' => $lesson->id,
                'completed_at' => Carbon::now(),
            ]);

            Meter::lessonComplete($completion); // emit the METER retrieval event
        }

        $this->syncCompletion();
        $this->refreshProgress();
    }

    /**
     * Flip the enrollment's completed_at through the evidence gate: earning
     * completion requires Course::completedBy() (lessons checked + coverage
     * on instrumented modules). Unchecking a lesson still revokes it, but
     * evidence aging out of the METER window does not — once earned,
     * completion is sticky against decay (retention is SRS's job, not a
     * reason to silently un-complete a course).
     */
    private function syncCompletion(): void
    {
        if (! $this->enrollment) {
            return;
        }

        $user = auth()->user();
        $lessonsDone = $this->course->progressFor($user) >= 1.0
            && $this->course->requiredLessons()->isNotEmpty();

        $this->enrollment->completed_at = match (true) {
            ! $lessonsDone => null,
            $this->enrollment->completed_at !== null => $this->enrollment->completed_at,
            default => $this->course->completedBy($user) ? Carbon::now() : null,
        };
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

    /** @return array<int, array> module_id => coverage evidence, for enrolled users' instrumented modules. */
    private function moduleEvidence(): array
    {
        $user = auth()->user();
        if (! $user || ! $this->enrollment) {
            return [];
        }

        return $this->course->modules
            ->filter(fn (Module $m) => $m->gymItems->isNotEmpty())
            ->mapWithKeys(fn (Module $m) => [$m->id => Report::moduleEvidence($m, $user)])
            ->all();
    }

    public function render()
    {
        $user = auth()->user();
        $required = $this->course->requiredLessons();
        $doneCount = collect($this->completed)->keys()
            ->intersect($required->pluck('id'))->count();
        $progress = $required->isNotEmpty() ? $doneCount / $required->count() : 0.0;

        $prereqs = $this->course->prerequisites->map(fn (Course $p) => [
            'course' => $p,
            'met' => $user
                ? Enrollment::where('user_id', $user->id)->where('course_id', $p->id)->whereNotNull('completed_at')->exists()
                : false,
        ]);

        $moduleEvidence = $this->moduleEvidence();

        // All lessons read but the evidence gate still open → name what's pending.
        $pendingEvidence = ($progress >= 1.0 && $this->enrollment && ! $this->enrollment->isComplete())
            ? collect($moduleEvidence)->reject(fn ($ev) => $ev['covered'])->pluck('title')->all()
            : [];

        return view('livewire.show-course', [
            'progress' => $progress,
            'requiredTotal' => $required->count(),
            'requiredDone' => $doneCount,
            'prereqs' => $prereqs,
            'prereqsMet' => $this->course->prerequisitesMetBy($user),
            'practiceGym' => $this->course->gyms->firstWhere('status', \App\Models\Gym::STATUS_PUBLISHED),
            'moduleEvidence' => $moduleEvidence,
            'pendingEvidence' => $pendingEvidence,
        ]);
    }
}
