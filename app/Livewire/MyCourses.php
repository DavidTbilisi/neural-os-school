<?php

namespace App\Livewire;

use App\Models\LessonCompletion;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class MyCourses extends Component
{
    public function render()
    {
        $user = auth()->user();

        $enrollments = $user->enrollments()
            ->with(['course.modules.lessons', 'course.domain'])
            ->latest('enrolled_at')
            ->get();

        $completedLessonIds = LessonCompletion::where('user_id', $user->id)
            ->pluck('lesson_id')->flip();

        $rows = $enrollments->map(function ($enrollment) use ($completedLessonIds) {
            $required = $enrollment->course->requiredLessons();
            $done = $required->filter(fn ($l) => $completedLessonIds->has($l->id))->count();

            return [
                'enrollment' => $enrollment,
                'course' => $enrollment->course,
                'progress' => $required->isNotEmpty() ? $done / $required->count() : 0.0,
                'requiredTotal' => $required->count(),
                'requiredDone' => $done,
            ];
        });

        return view('livewire.my-courses', ['rows' => $rows]);
    }
}
