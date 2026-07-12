<?php

namespace App\Livewire\Concerns;

use App\Models\Enrollment;
use App\Models\LessonCompletion;
use App\Support\Meter;
use Illuminate\Support\Carbon;

/**
 * Enrollment + lesson-completion + the evidence-gated completion sync, shared
 * by any full-page component holding `$course`/`$enrollment`/`$completed` —
 * ShowCourse and ShowLesson both mix this in so "mark lesson complete" and
 * the sticky completion gate have exactly one implementation.
 */
trait TracksCourseProgress
{
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
    protected function syncCompletion(): void
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

    protected function refreshProgress(): void
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
}
