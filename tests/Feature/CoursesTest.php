<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\ShowCourse;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Gym;
use App\Models\GymAttempt;
use App\Models\GymSession;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Module;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class CoursesTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, string $visibility = Page::VISIBILITY_PUBLIC): Page
    {
        return Page::create([
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'rel_path' => "x/{$slug}.md",
            'body_md' => "# {$slug}",
            'visibility' => $visibility,
        ]);
    }

    /** Build a course → module → lessons tree directly (bypassing the scaffolder). */
    private function course(string $slug, string $status = Course::STATUS_PUBLISHED): Course
    {
        return Course::create(['slug' => $slug, 'title' => ucwords($slug), 'status' => $status]);
    }

    private function lesson(Module $module, string $slug, bool $optional = false): Lesson
    {
        return Lesson::create([
            'module_id' => $module->id,
            'page_id' => $this->page($slug)->id,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'optional' => $optional,
            'sort' => $module->lessons()->count(),
        ]);
    }

    // ---- scaffolder -------------------------------------------------------

    public function test_scaffold_builds_modules_and_lessons_from_a_roadmap(): void
    {
        $this->page('foo');
        $this->page('bar');
        $this->page('baz');

        Page::create([
            'slug' => 'test-roadmap',
            'title' => 'Test Roadmap',
            'rel_path' => 'x/test-roadmap.md',
            'summary' => 'A roadmap for testing.',
            'body_md' => <<<'MD'
                # Test Roadmap

                **Summary**: intro links to [[foo]] that should be ignored (preamble).

                ## Tier 1
                - [[foo]] and [[bar]]

                ## Tier 2
                See [[baz]] and [[ghost-missing]].

                ## Notes
                Prose with no links — should be dropped.

                ## Related pages
                - [[foo]]
                MD,
        ]);

        $code = Artisan::call('courses:scaffold', ['roadmap' => 'test-roadmap']);
        $this->assertSame(0, $code);

        $course = Course::where('slug', 'test-roadmap')->firstOrFail();
        $this->assertSame('Test', $course->title);               // " Roadmap" stripped
        $this->assertSame(Course::STATUS_DRAFT, $course->status); // scaffolds land draft
        $this->assertSame('test-roadmap', $course->sourcePage->slug);

        $titles = $course->modules->pluck('title');
        $this->assertEqualsCanonicalizing(['Tier 1', 'Tier 2', 'Related pages'], $titles->all());
        $this->assertFalse($titles->contains('Notes')); // prose-only heading dropped

        $this->assertSame(4, $course->allLessons()->count()); // foo,bar + baz + foo
        $this->assertSame(2, $course->modules->firstWhere('title', 'Tier 1')->lessons->count());
    }

    public function test_scaffold_refuses_overwrite_without_force(): void
    {
        $this->page('foo');
        Page::create(['slug' => 'r-roadmap', 'title' => 'R Roadmap', 'rel_path' => 'x/r.md',
            'body_md' => "# R\n\n## M1\n[[foo]]"]);

        $this->assertSame(0, Artisan::call('courses:scaffold', ['roadmap' => 'r-roadmap']));
        $this->assertSame(1, Artisan::call('courses:scaffold', ['roadmap' => 'r-roadmap']));       // refused
        $this->assertSame(0, Artisan::call('courses:scaffold', ['roadmap' => 'r-roadmap', '--force' => true]));
        $this->assertSame(1, Course::where('slug', 'r-roadmap')->count());
    }

    // ---- public listing / visibility --------------------------------------

    public function test_index_lists_published_courses_only(): void
    {
        $this->course('shown-course', Course::STATUS_PUBLISHED);
        $this->course('hidden-course', Course::STATUS_DRAFT);

        $this->get('/courses')
            ->assertOk()
            ->assertSee('Shown-course')
            ->assertDontSee('Hidden-course');
    }

    public function test_draft_course_404s_for_guest_but_previews_for_staff(): void
    {
        $this->course('secret-course', Course::STATUS_DRAFT);

        $this->get('/courses/secret-course')->assertNotFound();

        $staff = User::factory()->create(['role' => UserRole::Editor]);
        $this->actingAs($staff)->get('/courses/secret-course')->assertOk()->assertSee('staff preview');
    }

    // ---- enrollment + progress -------------------------------------------

    public function test_learner_enrolls_marks_lessons_and_completes_course(): void
    {
        $course = $this->course('progress-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $l1 = $this->lesson($m1, 'lesson-one');
        $l2 = $this->lesson($m1, 'lesson-two');
        $optional = $this->lesson($m1, 'lesson-opt', optional: true);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);

        $component = Livewire::test(ShowCourse::class, ['slug' => 'progress-course'])
            ->call('enroll');

        $this->assertTrue($learner->isEnrolledIn($course->fresh()));

        // One of two required lessons → 50%, not complete.
        $component->call('toggleLesson', $l1->id);
        $this->assertEqualsWithDelta(0.5, $course->fresh()->progressFor($learner), 0.001);
        $this->assertNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);

        // Optional lesson doesn't move required progress.
        $component->call('toggleLesson', $optional->id);
        $this->assertEqualsWithDelta(0.5, $course->fresh()->progressFor($learner), 0.001);

        // Both required done → 100% and enrollment marked complete.
        $component->call('toggleLesson', $l2->id);
        $this->assertEqualsWithDelta(1.0, $course->fresh()->progressFor($learner), 0.001);
        $this->assertNotNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);

        // Toggling a done lesson off un-completes the course.
        $component->call('toggleLesson', $l1->id);
        $this->assertNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);
        $this->assertSame(2, LessonCompletion::where('user_id', $learner->id)->count()); // l2 + optional
    }

    public function test_toggle_ignored_when_not_enrolled(): void
    {
        $course = $this->course('gated-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $l1 = $this->lesson($m1, 'gated-lesson');

        $learner = User::factory()->create(['role' => UserRole::Learner]);

        Livewire::actingAs($learner)->test(ShowCourse::class, ['slug' => 'gated-course'])
            ->call('toggleLesson', $l1->id);

        $this->assertSame(0, LessonCompletion::count());
    }

    // ---- soft prerequisites ----------------------------------------------

    public function test_prerequisites_are_shown_but_do_not_block_enrollment(): void
    {
        $intro = $this->course('intro-course');
        $advanced = $this->course('advanced-course');
        $advanced->prerequisites()->attach($intro->id);

        $learner = User::factory()->create(['role' => UserRole::Learner]);

        // Prereq unmet (intro not completed) but enroll still works (soft gate).
        $this->assertFalse($advanced->prerequisitesMetBy($learner));

        Livewire::actingAs($learner)->test(ShowCourse::class, ['slug' => 'advanced-course'])
            ->assertSee('Intro-course')       // prerequisite surfaced
            ->assertSee('Recommended first')
            ->call('enroll');

        $this->assertTrue($learner->fresh()->isEnrolledIn($advanced->fresh()));
    }

    // ---- evidence gate ------------------------------------------------------

    /** Attach a recognition gym to the course with its items tagged to the module. */
    private function instrument(Course $course, Module $module): Gym
    {
        $gym = Gym::create([
            'slug' => $course->slug.'-gym', 'title' => 'Gate Gym', 'mode' => 'recognition',
            'timer_seconds' => 8, 'round_count' => 2, 'latency_target_ms' => 6000,
            'pass_accuracy' => 0.80, 'promote_accuracy' => 0.85, 'status' => Gym::STATUS_PUBLISHED,
            'course_id' => $course->id,
        ]);
        foreach (range(0, 1) as $i) {
            $gym->items()->create([
                'prompt' => "Q{$i}", 'choices' => ['A', 'B'], 'correct' => 'A',
                'module_id' => $module->id, 'sort' => $i,
            ]);
        }

        return $gym;
    }

    /** Drill telemetry: sessions × attempts at a given accuracy (coverage reads gym_attempts). */
    private function drill(User $u, Gym $gym, int $sessions, int $perSession, float $accuracy, int $latencyMs = 1000): void
    {
        $item = $gym->items->first();
        for ($s = 0; $s < $sessions; $s++) {
            $session = GymSession::create(['user_id' => $u->id, 'gym_id' => $gym->id, 'started_at' => now()]);
            for ($i = 0; $i < $perSession; $i++) {
                $correct = $i < (int) round($perSession * $accuracy);
                GymAttempt::create([
                    'gym_session_id' => $session->id, 'gym_item_id' => $item->id,
                    'selected' => $correct ? 'A' : 'B', 'is_correct' => $correct, 'latency_ms' => $latencyMs,
                ]);
            }
        }
    }

    public function test_reading_alone_does_not_complete_an_instrumented_course(): void
    {
        $course = $this->course('gate-course');
        $m1 = $course->modules()->create(['title' => 'Gated Module', 'sort' => 0]);
        $l1 = $this->lesson($m1, 'gate-lesson');
        $gym = $this->instrument($course, $m1);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);

        // All lessons checked → exposure done, but the gate stays shut.
        Livewire::test(ShowCourse::class, ['slug' => 'gate-course'])
            ->call('enroll')
            ->call('toggleLesson', $l1->id)
            ->assertSee('Evidence pending')
            ->assertSee('Gated Module')
            ->assertSee('0/10 reps');

        $this->assertEqualsWithDelta(1.0, $course->fresh()->progressFor($learner), 0.001);
        $this->assertNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);

        // Coverage earned in the gym → the next visit closes the loop.
        $this->drill($learner, $gym, sessions: 2, perSession: 6, accuracy: 1.0);

        Livewire::test(ShowCourse::class, ['slug' => 'gate-course'])
            ->assertSee('✓ Covered')
            ->assertSee('✓ Completed');

        $this->assertNotNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);
    }

    public function test_evidence_alone_does_not_complete_without_reading(): void
    {
        $course = $this->course('drill-first-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $l1 = $this->lesson($m1, 'drill-first-lesson');
        $gym = $this->instrument($course, $m1);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);

        $component = Livewire::test(ShowCourse::class, ['slug' => 'drill-first-course'])->call('enroll');
        $this->drill($learner, $gym, sessions: 2, perSession: 6, accuracy: 1.0);

        // Covered but unread → not complete (the checkbox still means something).
        $this->assertTrue($m1->fresh()->completedBy($learner) === false);
        $this->assertNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);

        // Checking the last lesson completes immediately — evidence was already in.
        $component->call('toggleLesson', $l1->id);
        $this->assertNotNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);
    }

    public function test_accuracy_below_pass_keeps_the_gate_shut(): void
    {
        $course = $this->course('miss-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $l1 = $this->lesson($m1, 'miss-lesson');
        $gym = $this->instrument($course, $m1);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);
        $this->drill($learner, $gym, sessions: 2, perSession: 8, accuracy: 0.75); // pass is .80 → rung 3

        Livewire::test(ShowCourse::class, ['slug' => 'miss-course'])
            ->call('enroll')
            ->call('toggleLesson', $l1->id)
            ->assertSee('L3 — target is L4 Classifiable');

        $this->assertNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);
    }

    public function test_a_reflex_module_demands_speed_as_well_as_accuracy(): void
    {
        $course = $this->course('reflex-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'target_rung' => 7, 'sort' => 0]);
        $l1 = $this->lesson($m1, 'reflex-lesson');
        $gym = $this->instrument($course, $m1); // latency target 6000ms

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);

        // Perfect accuracy but slow → rung 5 Operational, short of Reflexive.
        $this->drill($learner, $gym, sessions: 2, perSession: 6, accuracy: 1.0, latencyMs: 8000);

        Livewire::test(ShowCourse::class, ['slug' => 'reflex-course'])
            ->call('enroll')
            ->call('toggleLesson', $l1->id)
            ->assertSee('L5 — target is L7 Reflexive');
        $this->assertNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);

        // Enough fast reps pull the median under the target → gate opens.
        $this->drill($learner, $gym, sessions: 2, perSession: 14, accuracy: 1.0, latencyMs: 1000);

        Livewire::test(ShowCourse::class, ['slug' => 'reflex-course'])
            ->assertSee('✓ Covered · L7');
        $this->assertNotNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);
    }

    public function test_a_target_beyond_the_gym_ceiling_reads_uncertifiable(): void
    {
        $course = $this->course('deep-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'target_rung' => 8, 'sort' => 0]); // Transferable
        $l1 = $this->lesson($m1, 'deep-lesson');
        $gym = $this->instrument($course, $m1);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);
        $this->drill($learner, $gym, sessions: 2, perSession: 6, accuracy: 1.0); // best a drill can show: rung 7

        Livewire::test(ShowCourse::class, ['slug' => 'deep-course'])
            ->call('enroll')
            ->call('toggleLesson', $l1->id)
            ->assertSee('needs a deeper instrument');

        $this->assertNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);
    }

    public function test_completion_is_sticky_when_evidence_ages_out(): void
    {
        $course = $this->course('sticky-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $l1 = $this->lesson($m1, 'sticky-lesson');
        $gym = $this->instrument($course, $m1);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);
        $this->drill($learner, $gym, sessions: 2, perSession: 6, accuracy: 1.0);

        Livewire::test(ShowCourse::class, ['slug' => 'sticky-course'])
            ->call('enroll')
            ->call('toggleLesson', $l1->id);
        $this->assertNotNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);

        // Attempts slide out of the METER window — completion must not silently revoke…
        $this->travel(40)->days();
        Livewire::test(ShowCourse::class, ['slug' => 'sticky-course'])->assertSee('✓ Completed');
        $this->assertNotNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);

        // …but unchecking a lesson still does, as before.
        Livewire::test(ShowCourse::class, ['slug' => 'sticky-course'])->call('toggleLesson', $l1->id);
        $this->assertNull(Enrollment::firstWhere('course_id', $course->id)->completed_at);
    }

    // ---- cross-links / admin ---------------------------------------------

    public function test_wiki_page_shows_part_of_course_crosslink(): void
    {
        $course = $this->course('linking-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $lesson = $this->lesson($m1, 'crosslinked-page');

        $this->get('/wiki/crosslinked-page')
            ->assertOk()
            ->assertSee('Part of')
            ->assertSee('Linking-course');
    }

    public function test_course_admin_pages_render_for_staff(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $course = $this->course('admin-course', Course::STATUS_DRAFT);
        $course->modules()->create(['title' => 'M1', 'sort' => 0]);

        $this->actingAs($admin);
        $this->get('/admin/courses')->assertOk();
        $this->get('/admin/courses/create')->assertOk();
        $this->get("/admin/courses/{$course->id}/edit")->assertOk(); // mounts ModulesRelationManager
    }
}
