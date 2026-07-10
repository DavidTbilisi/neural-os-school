<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Drawing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The per-course Excalidraw whiteboard. Hosted as a plain Blade page (not Livewire)
 * so the React island owns its DOM subtree without morphdom interference. The scene
 * loads/saves as raw Excalidraw JSON over a small JSON endpoint.
 */
class SketchpadController extends Controller
{
    public function show(string $slug): View
    {
        $course = $this->resolveCourse($slug);

        $drawing = Drawing::where('user_id', auth()->id())
            ->where('course_id', $course->id)
            ->first();

        return view('courses.sketchpad', [
            'course' => $course,
            // Decoded scene (or null) — embedded via @json, which hex-escapes
            // </script> so a shape's text can't break out of the data island.
            'scene' => $drawing?->scene ? json_decode($drawing->scene, true) : null,
        ]);
    }

    public function save(Request $request, string $slug): JsonResponse
    {
        $course = $this->resolveCourse($slug);

        $validated = $request->validate([
            // Excalidraw serializeAsJSON output; cap to keep a runaway scene bounded.
            'scene' => ['required', 'string', 'max:8000000', 'json'],
        ]);

        $drawing = Drawing::updateOrCreate(
            ['user_id' => auth()->id(), 'course_id' => $course->id],
            ['scene' => $validated['scene']],
        );

        return response()->json([
            'ok' => true,
            'saved_at' => $drawing->updated_at?->toIso8601String(),
        ]);
    }

    /** Published courses are open to any signed-in learner; drafts are staff-only. */
    private function resolveCourse(string $slug): Course
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        abort_unless(
            $course->isPublished() || (auth()->user()?->isStaff() ?? false),
            404,
        );

        return $course;
    }
}
