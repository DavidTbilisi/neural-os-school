<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\ShowLesson;
use App\Models\Course;
use App\Models\Gym;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowLessonTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, string $visibility = Page::VISIBILITY_PUBLIC): Page
    {
        return Page::create([
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'rel_path' => "x/{$slug}.md",
            'body_md' => "# {$slug}\n\nBody for {$slug}.",
            'visibility' => $visibility,
        ]);
    }

    private function course(string $slug, string $status = Course::STATUS_PUBLISHED): Course
    {
        return Course::create(['slug' => $slug, 'title' => ucwords($slug), 'status' => $status]);
    }

    private function lesson(Module $module, string $slug, bool $optional = false, string $visibility = Page::VISIBILITY_PUBLIC): Lesson
    {
        return Lesson::create([
            'module_id' => $module->id,
            'page_id' => $this->page($slug, $visibility)->id,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'optional' => $optional,
            'sort' => $module->lessons()->count(),
        ]);
    }

    public function test_in_course_lesson_renders_reader_content_via_wiki_renderer(): void
    {
        $course = $this->course('reader-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $this->lesson($m1, 'reader-lesson');

        $this->get('/courses/reader-course/lessons/reader-lesson')
            ->assertOk()
            ->assertSee('Reader Lesson')
            ->assertSee('Body for reader-lesson');
    }

    public function test_prev_next_are_null_at_the_edges(): void
    {
        $course = $this->course('nav-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $first = $this->lesson($m1, 'nav-first');
        $middle = $this->lesson($m1, 'nav-middle');
        $last = $this->lesson($m1, 'nav-last');

        $firstComponent = Livewire::test(ShowLesson::class, ['courseSlug' => 'nav-course', 'lessonSlug' => 'nav-first']);
        $this->assertNull($firstComponent->get('prevLesson'));
        $this->assertSame($middle->id, $firstComponent->get('nextLesson')->id);

        $middleComponent = Livewire::test(ShowLesson::class, ['courseSlug' => 'nav-course', 'lessonSlug' => 'nav-middle']);
        $this->assertSame($first->id, $middleComponent->get('prevLesson')->id);
        $this->assertSame($last->id, $middleComponent->get('nextLesson')->id);

        $lastComponent = Livewire::test(ShowLesson::class, ['courseSlug' => 'nav-course', 'lessonSlug' => 'nav-last']);
        $this->assertSame($middle->id, $lastComponent->get('prevLesson')->id);
        $this->assertNull($lastComponent->get('nextLesson'));
    }

    public function test_prev_next_skips_an_unpublished_lesson_for_a_learner_but_not_staff(): void
    {
        $course = $this->course('skip-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $first = $this->lesson($m1, 'skip-first');
        $hidden = $this->lesson($m1, 'skip-hidden', visibility: Page::VISIBILITY_PRIVATE);
        $last = $this->lesson($m1, 'skip-last');

        // Learner: hidden lesson is skipped, first's "next" is last.
        $learnerView = Livewire::test(ShowLesson::class, ['courseSlug' => 'skip-course', 'lessonSlug' => 'skip-first']);
        $this->assertSame($last->id, $learnerView->get('nextLesson')->id);

        // Staff: hidden lesson is included in the sequence.
        $staff = User::factory()->create(['role' => UserRole::Editor]);
        $staffView = Livewire::actingAs($staff)->test(ShowLesson::class, ['courseSlug' => 'skip-course', 'lessonSlug' => 'skip-first']);
        $this->assertSame($hidden->id, $staffView->get('nextLesson')->id);
    }

    public function test_optional_lessons_are_included_in_the_reading_sequence(): void
    {
        $course = $this->course('optional-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $required = $this->lesson($m1, 'opt-required');
        $optional = $this->lesson($m1, 'opt-optional', optional: true);

        $component = Livewire::test(ShowLesson::class, ['courseSlug' => 'optional-course', 'lessonSlug' => 'opt-required']);
        $this->assertSame($optional->id, $component->get('nextLesson')->id);
    }

    public function test_sidebar_shows_completion_state_and_highlights_current_lesson(): void
    {
        $course = $this->course('sidebar-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $l1 = $this->lesson($m1, 'sidebar-one');
        $this->lesson($m1, 'sidebar-two');

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);

        Livewire::test(ShowLesson::class, ['courseSlug' => 'sidebar-course', 'lessonSlug' => 'sidebar-one'])
            ->call('enroll')
            ->call('toggleLesson', $l1->id)
            ->assertSet('completed', [$l1->id => true]);
    }

    public function test_mark_complete_from_the_lesson_page_reuses_toggle_lesson(): void
    {
        $course = $this->course('mark-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $l1 = $this->lesson($m1, 'mark-lesson');

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);

        Livewire::test(ShowLesson::class, ['courseSlug' => 'mark-course', 'lessonSlug' => 'mark-lesson'])
            ->call('enroll')
            ->call('toggleLesson', $l1->id)
            ->assertSee('Marked complete');

        $this->assertEqualsWithDelta(1.0, $course->fresh()->progressFor($learner), 0.001);
    }

    public function test_draft_course_404s_for_guest_but_previews_for_staff(): void
    {
        $course = $this->course('secret-course', Course::STATUS_DRAFT);
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $this->lesson($m1, 'secret-lesson');

        $this->get('/courses/secret-course/lessons/secret-lesson')->assertNotFound();

        $staff = User::factory()->create(['role' => UserRole::Editor]);
        $this->actingAs($staff)
            ->get('/courses/secret-course/lessons/secret-lesson')
            ->assertOk();
    }

    public function test_unpublished_lesson_page_404s_for_a_learner(): void
    {
        $course = $this->course('private-lesson-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $this->lesson($m1, 'private-lesson', visibility: Page::VISIBILITY_PRIVATE);

        $this->get('/courses/private-lesson-course/lessons/private-lesson')->assertNotFound();
    }

    public function test_check_block_renders_only_when_the_lesson_has_tagged_items(): void
    {
        $course = $this->course('check-visibility-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $bare = $this->lesson($m1, 'bare-check-lesson');
        $tagged = $this->lesson($m1, 'tagged-check-lesson');

        $gym = Gym::create([
            'slug' => 'check-visibility-gym', 'title' => 'Gym', 'mode' => 'recognition',
            'timer_seconds' => 8, 'round_count' => 1, 'latency_target_ms' => 6000,
            'pass_accuracy' => 0.80, 'promote_accuracy' => 0.85, 'status' => Gym::STATUS_PUBLISHED,
            'course_id' => $course->id,
        ]);
        $gym->items()->create([
            'prompt' => 'Q', 'choices' => ['A', 'B'], 'correct' => 'A',
            'module_id' => $m1->id, 'lesson_id' => $tagged->id, 'sort' => 0,
        ]);

        $this->get('/courses/check-visibility-course/lessons/bare-check-lesson')
            ->assertOk()
            ->assertDontSee('Check your understanding');

        $this->get('/courses/check-visibility-course/lessons/tagged-check-lesson')
            ->assertOk()
            ->assertSee('Check your understanding');
    }

    public function test_standalone_wiki_route_is_unaffected_by_the_refactor(): void
    {
        $course = $this->course('standalone-course');
        $m1 = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $this->lesson($m1, 'standalone-lesson');

        $this->get('/wiki/standalone-lesson')
            ->assertOk()
            ->assertSee('Standalone Lesson')
            ->assertSee('Body for standalone-lesson')
            ->assertSee('Part of')
            ->assertSee('Standalone-course');
    }
}
