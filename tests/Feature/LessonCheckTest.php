<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\LessonCheck;
use App\Livewire\ShowLesson;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Gym;
use App\Models\GymAttempt;
use App\Models\GymSession;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Page;
use App\Models\SrsCard;
use App\Models\User;
use App\Services\Meter\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LessonCheckTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug): Page
    {
        return Page::create([
            'slug' => $slug, 'title' => ucwords($slug), 'rel_path' => "x/{$slug}.md", 'body_md' => "# {$slug}",
            'visibility' => Page::VISIBILITY_PUBLIC,
        ]);
    }

    private function courseWithLesson(string $slug): array
    {
        $course = Course::create(['slug' => "{$slug}-course", 'title' => 'Check Course', 'status' => Course::STATUS_PUBLISHED]);
        $module = $course->modules()->create(['title' => 'Module A', 'sort' => 0]);
        $lesson = Lesson::create([
            'module_id' => $module->id, 'page_id' => $this->page($slug)->id,
            'title' => ucwords($slug), 'sort' => 0,
        ]);

        return [$course, $module, $lesson];
    }

    /** A course Gym with $count items tagged to $lesson (and its parent module). */
    private function taggedGym(Course $course, Module $module, Lesson $lesson, int $count = 3): Gym
    {
        $gym = Gym::create([
            'slug' => $course->slug.'-gym', 'title' => 'Course Gym', 'mode' => 'recognition',
            'timer_seconds' => 8, 'round_count' => $count, 'latency_target_ms' => 6000,
            'pass_accuracy' => 0.80, 'promote_accuracy' => 0.85, 'status' => Gym::STATUS_PUBLISHED,
            'course_id' => $course->id,
        ]);

        for ($i = 0; $i < $count; $i++) {
            $gym->items()->create([
                'prompt' => "Q{$i}", 'choices' => ['A', 'B'], 'correct' => 'A',
                'module_id' => $module->id, 'lesson_id' => $lesson->id, 'sort' => $i,
            ]);
        }

        return $gym;
    }

    public function test_no_check_block_when_lesson_has_no_tagged_items(): void
    {
        [$course, $module, $lesson] = $this->courseWithLesson('bare-lesson');

        $component = Livewire::test(LessonCheck::class, ['forLesson' => $lesson]);
        $this->assertSame([], $component->get('order'));

        $this->get("/courses/{$course->slug}/lessons/bare-lesson")
            ->assertOk()
            ->assertDontSee('Check your understanding');
    }

    public function test_answering_a_lesson_check_creates_a_gym_session_and_attempts_tagged_correctly(): void
    {
        [$course, $module, $lesson] = $this->courseWithLesson('answer-lesson');
        $gym = $this->taggedGym($course, $module, $lesson, count: 2);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);

        $component = Livewire::test(LessonCheck::class, ['forLesson' => $lesson])
            ->call('start')
            ->call('answer', 'A', 500)
            ->call('next')
            ->call('answer', 'A', 500)
            ->call('next');

        $this->assertSame(1, GymSession::where('user_id', $learner->id)->where('gym_id', $gym->id)->count());
        $session = GymSession::where('user_id', $learner->id)->first();
        $this->assertSame(2, $session->attempts()->count());
        $this->assertTrue(
            $session->attempts->pluck('gym_item_id')
                ->diff($gym->items->whereNotNull('lesson_id')->pluck('id'))
                ->isEmpty()
        );
        $this->assertSame('done', $component->get('phase'));
        $this->assertNotNull($session->fresh()->completed_at); // GymScoring::finalizeSession ran
    }

    public function test_lesson_check_reps_roll_up_into_report_module_evidence(): void
    {
        [$course, $module, $lesson] = $this->courseWithLesson('coverage-lesson');
        $gym = $this->taggedGym($course, $module, $lesson, count: 6);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);

        // 2 separate check play-throughs, 6 reps each (12 total, ≥ MIN_SIGNAL), all correct.
        foreach ([1, 2] as $pass) {
            $component = Livewire::test(LessonCheck::class, ['forLesson' => $lesson])->call('start');
            foreach (range(1, 6) as $i) {
                $component->call('answer', 'A', 500)->call('next');
            }
        }

        $evidence = Report::moduleEvidence($module->fresh(), $learner);
        $this->assertTrue($evidence['covered']);

        // completedBy() also requires the lesson's exposure checkbox — coverage alone isn't enough.
        \App\Models\LessonCompletion::create(['user_id' => $learner->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        $this->assertTrue($module->fresh()->completedBy($learner));
    }

    public function test_a_lesson_check_rep_schedules_an_srs_card(): void
    {
        [$course, $module, $lesson] = $this->courseWithLesson('srs-lesson');
        $gym = $this->taggedGym($course, $module, $lesson, count: 1);
        $item = $gym->items->first();

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);

        Livewire::test(LessonCheck::class, ['forLesson' => $lesson])
            ->call('start')
            ->call('answer', 'A', 500);

        $this->assertSame(1, SrsCard::where('user_id', $learner->id)->where('gym_item_id', $item->id)->count());
    }

    public function test_guest_hitting_start_is_redirected_to_login_and_no_session_is_created(): void
    {
        [$course, $module, $lesson] = $this->courseWithLesson('guest-lesson');
        $this->taggedGym($course, $module, $lesson, count: 1);

        Livewire::test(LessonCheck::class, ['forLesson' => $lesson])
            ->call('start')
            ->assertRedirect(route('login'));

        $this->assertSame(0, GymSession::count());
    }

    public function test_lesson_check_answering_does_not_directly_touch_enrollment_completed_at(): void
    {
        [$course, $module, $lesson] = $this->courseWithLesson('sticky-check-lesson');
        $gym = $this->taggedGym($course, $module, $lesson, count: 12);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);

        Livewire::test(ShowLesson::class, ['courseSlug' => $course->slug, 'lessonSlug' => 'sticky-check-lesson'])
            ->call('enroll')
            ->call('toggleLesson', $lesson->id);

        // Drill enough reps/sessions to satisfy coverage, but only through LessonCheck directly —
        // Enrollment.completed_at must stay whatever it was until a course/lesson view re-syncs it.
        foreach ([1, 2] as $pass) {
            $component = Livewire::test(LessonCheck::class, ['forLesson' => $lesson])->call('start');
            foreach (range(1, 6) as $i) {
                $component->call('answer', 'A', 500)->call('next');
            }
        }

        $this->assertTrue(Report::moduleEvidence($module->fresh(), $learner)['covered']);
        $this->assertNull(Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->first()->completed_at);

        // Re-visiting the lesson (which calls syncCompletion()) is what flips it.
        Livewire::test(ShowLesson::class, ['courseSlug' => $course->slug, 'lessonSlug' => 'sticky-check-lesson']);
        $this->assertNotNull(Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->first()->completed_at);
    }
}
