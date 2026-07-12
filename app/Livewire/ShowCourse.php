<?php

namespace App\Livewire;

use App\Livewire\Concerns\TracksCourseProgress;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Services\Meter\Report;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class ShowCourse extends Component
{
    use TracksCourseProgress;

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
