<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\ShowCourse;
use App\Models\Course;
use App\Models\Enrollment;
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
