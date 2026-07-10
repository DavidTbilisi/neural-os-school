<?php

namespace App\Livewire;

use App\Models\Course;
use App\Models\LessonCompletion;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class Courses extends Component
{
    public function render()
    {
        $courses = Course::published()
            ->with(['domain', 'modules.lessons', 'prerequisites'])
            ->orderBy('sort')
            ->orderBy('title')
            ->get();

        $user = auth()->user();

        $enrolledIds = $user
            ? $user->enrollments()->pluck('course_id')->flip()
            : collect();

        $completedLessonIds = $user
            ? LessonCompletion::where('user_id', $user->id)->pluck('lesson_id')->flip()
            : collect();

        $cards = $courses->map(function (Course $course) use ($user, $enrolledIds, $completedLessonIds) {
            $required = $course->requiredLessons();
            $done = $required->filter(fn ($l) => $completedLessonIds->has($l->id))->count();

            return [
                'course' => $course,
                'lessonCount' => $course->allLessons()->count(),
                'moduleCount' => $course->modules->count(),
                'enrolled' => $enrolledIds->has($course->id),
                'progress' => $required->isNotEmpty() ? $done / $required->count() : 0.0,
                'prereqsMet' => $course->prerequisitesMetBy($user),
            ];
        });

        return view('livewire.courses', ['cards' => $cards]);
    }
}
