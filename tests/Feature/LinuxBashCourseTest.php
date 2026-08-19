<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Gym;
use App\Models\GymItem;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\BashPatternGymSeeder;
use Database\Seeders\LinuxBashCourseSeeder;
use Database\Seeders\WikiReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Linux: Bash Scripting course and its practice gym. Covers the two
 * invariants that are easy to break by editing a seeder: the curriculum stays
 * enrollment-safe across a re-seed, and every gym item's module tag agrees with
 * its lesson tag (the gym_items.lesson_id contract — a lesson-tagged item must
 * carry that lesson's parent module, or module-scoped evidence misattributes).
 */
class LinuxBashCourseTest extends TestCase
{
    use RefreshDatabase;

    /** The lesson pages the seeders reference, published so upsertCourse can see them. */
    private function seedPages(): void
    {
        $this->seed(WikiReferenceSeeder::class); // domains, for the pages' domain_id FK

        $slugs = [
            'bash-atlas', 'shell-and-command-anatomy', 'expansion-order',
            'quoting-rules', 'word-splitting-and-ifs', 'parameter-expansion',
            'command-substitution-and-arithmetic', 'globbing', 'redirection',
            'pipes-and-process-substitution', 'here-documents',
            'exit-status-and-control-flow', 'test-and-double-bracket', 'loops',
            'case-statements', 'functions-and-scope', 'arrays-and-associative-arrays',
            'positional-parameters-and-getopts', 'variables-and-the-environment',
            'processes-and-job-control', 'subshells-and-execution-context',
            'signals-and-traps', 'grep-and-regex', 'sed', 'awk', 'text-toolkit',
            'find-and-xargs', 'strict-mode', 'script-structure-and-cli-design',
            'debugging-bash', 'shellcheck-and-static-analysis',
            'bash-pitfalls-catalog', 'bash-drill-ladder',
        ];

        foreach ($slugs as $slug) {
            Page::create([
                'slug' => $slug,
                'title' => ucwords(str_replace('-', ' ', $slug)),
                'rel_path' => "linux/{$slug}.md",
                'body_md' => "# {$slug}",
                'domain_id' => 10,
                'visibility' => Page::VISIBILITY_PUBLIC,
            ]);
        }
    }

    public function test_course_seeds_every_lesson_and_publishes(): void
    {
        $this->seedPages();
        $this->seed(LinuxBashCourseSeeder::class);

        $course = Course::where('slug', 'linux-bash')->sole();

        $this->assertSame(Course::STATUS_PUBLISHED, $course->status);
        $this->assertSame(9, $course->modules()->count());
        $this->assertSame(33, Lesson::whereIn('module_id', $course->modules()->pluck('id'))->count());

        // Module 1 is short by design; the reader must clear expansion first.
        $this->assertSame('The shell as a language', $course->modules()->where('sort', 0)->sole()->title);
        // Quoting is the gym's target module — recognition has to be reflexive.
        $this->assertSame(7, (int) $course->modules()->where('title', 'Quoting and expansion')->sole()->target_rung);
        // Reference & practice is the optional tail, like every other course here.
        $this->assertTrue($course->modules()->where('title', 'Reference and practice')->sole()
            ->lessons()->get()->every->optional);
    }

    public function test_gym_items_are_tagged_to_their_lessons_parent_module(): void
    {
        $this->seedPages();
        $this->seed(LinuxBashCourseSeeder::class);
        $this->seed(BashPatternGymSeeder::class);

        $gym = Gym::where('slug', 'bash-pattern-gym')->sole();

        $this->assertSame(31, $gym->items()->count());
        $this->assertSame(0, $gym->items()->whereNull('module_id')->count());
        $this->assertSame(0, $gym->items()->whereNull('lesson_id')->count());

        foreach ($gym->items()->with('lesson')->get() as $item) {
            $this->assertSame(
                $item->lesson->module_id,
                $item->module_id,
                "item #{$item->sort} is tagged to a module that is not its lesson's parent",
            );
            $this->assertContains($item->correct, $item->choices, "item #{$item->sort} cannot be answered correctly");
        }
    }

    public function test_every_blind_spot_family_has_more_than_one_item(): void
    {
        $this->seedPages();
        $this->seed(LinuxBashCourseSeeder::class);
        $this->seed(BashPatternGymSeeder::class);

        $gym = Gym::where('slug', 'bash-pattern-gym')->sole();
        $this->assertTrue($gym->blind_spot_floor);

        // With the floor on, `correct` values are the categories a run is judged
        // by. A one-item family is zeroed by a single unlucky miss, which would
        // cap the rung for noise rather than for a real hole.
        $counts = $gym->items()->get()->countBy('correct');
        $this->assertSame(12, $counts->count());
        foreach ($counts as $family => $n) {
            $this->assertGreaterThanOrEqual(2, $n, "defect family '{$family}' has only {$n} item(s)");
        }
    }

    public function test_reseed_is_enrollment_safe_and_keeps_gym_tags(): void
    {
        $this->seedPages();
        $this->seed(LinuxBashCourseSeeder::class);
        $this->seed(BashPatternGymSeeder::class);

        $course = Course::where('slug', 'linux-bash')->sole();
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
        $lesson = Lesson::whereHas('page', fn ($q) => $q->where('slug', 'quoting-rules'))->sole();
        LessonCompletion::create(['user_id' => $learner->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);

        $tagsBefore = GymItem::orderBy('sort')->pluck('module_id')->all();
        $itemIdsBefore = GymItem::orderBy('sort')->pluck('id')->all();

        $this->seed(LinuxBashCourseSeeder::class);

        $this->assertSame($course->id, Course::where('slug', 'linux-bash')->sole()->id);
        $this->assertSame(1, Enrollment::where('user_id', $learner->id)->count());
        $this->assertSame(1, LessonCompletion::where('user_id', $learner->id)->count());
        $this->assertSame($lesson->id, Lesson::whereHas('page', fn ($q) => $q->where('slug', 'quoting-rules'))->sole()->id);

        // Modules are upserted in place, so the gym's tags still point at them.
        $this->assertSame($tagsBefore, GymItem::orderBy('sort')->pluck('module_id')->all());
        $this->assertSame($itemIdsBefore, GymItem::orderBy('sort')->pluck('id')->all());

        // And the course seeder mounts the gym as Practice once it exists.
        $this->assertSame($course->id, Gym::where('slug', 'bash-pattern-gym')->sole()->course_id);
    }
}
