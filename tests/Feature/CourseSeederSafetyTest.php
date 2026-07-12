<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Module;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\Concerns\UpsertsCourseCurriculum;
use Database\Seeders\DsaCourseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Test double: the upsert trait driven by swappable curriculum rows. */
class FakeCourseSeeder extends Seeder
{
    use UpsertsCourseCurriculum;

    public function __construct(private array $rows) {}

    public function run(): array
    {
        return $this->upsertCourse('safety-course', [
            'title' => 'Safety Course',
            'status' => Course::STATUS_PUBLISHED,
        ], $this->rows);
    }
}

class CourseSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug): Page
    {
        return Page::create([
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'rel_path' => "x/{$slug}.md",
            'body_md' => "# {$slug}",
            'visibility' => Page::VISIBILITY_PUBLIC,
        ]);
    }

    private function complete(User $user, string $pageSlug): LessonCompletion
    {
        $lesson = Lesson::whereHas('page', fn ($q) => $q->where('slug', $pageSlug))->sole();

        return LessonCompletion::create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id, 'completed_at' => now(),
        ]);
    }

    public function test_reseeding_preserves_ids_enrollments_and_completions(): void
    {
        $this->page('p-one');
        $this->page('p-two');
        $rows = [['M1', 'First module', ['p-one', 'p-two']]];

        (new FakeCourseSeeder($rows))->run();
        $course = Course::where('slug', 'safety-course')->sole();
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
        $this->complete($learner, 'p-one');

        $moduleIds = $course->modules()->pluck('id');
        $lessonIds = $course->lessons()->orderBy('lessons.sort')->pluck('lessons.id');

        (new FakeCourseSeeder($rows))->run();

        $this->assertSame($course->id, Course::where('slug', 'safety-course')->sole()->id);
        $this->assertEquals($moduleIds, $course->modules()->pluck('id'));
        $this->assertEquals($lessonIds, $course->lessons()->orderBy('lessons.sort')->pluck('lessons.id'));
        $this->assertSame(1, Enrollment::where('user_id', $learner->id)->count());
        $this->assertSame(1, LessonCompletion::where('user_id', $learner->id)->count());
    }

    public function test_a_lesson_moving_between_modules_keeps_its_completions(): void
    {
        $this->page('p-move');
        $this->page('p-stay');
        (new FakeCourseSeeder([['M1', '', ['p-move', 'p-stay']], ['M2', '', []]]))->run();

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $completion = $this->complete($learner, 'p-move');
        $lessonId = $completion->lesson_id;

        (new FakeCourseSeeder([['M1', '', ['p-stay']], ['M2', '', ['p-move']]]))->run();

        $lesson = Lesson::findOrFail($lessonId); // same row, new parent
        $this->assertSame('M2', $lesson->module->title);
        $this->assertNotNull($completion->fresh());
    }

    public function test_what_leaves_the_curriculum_is_pruned(): void
    {
        $this->page('p-keep');
        $this->page('p-drop');
        (new FakeCourseSeeder([['M1', '', ['p-keep']], ['M2', '', ['p-drop']]]))->run();

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $dropped = $this->complete($learner, 'p-drop');

        (new FakeCourseSeeder([['M1', '', ['p-keep']]]))->run();

        $course = Course::where('slug', 'safety-course')->sole();
        $this->assertSame(['M1'], $course->modules()->pluck('title')->all());
        $this->assertSame(1, $course->lessons()->count());
        $this->assertNull($dropped->fresh()); // its lesson is gone, so is the completion
    }

    public function test_curriculum_flags_update_in_place(): void
    {
        $this->page('p-flag');
        (new FakeCourseSeeder([['M1', '', ['p-flag']]]))->run();
        $module = Module::where('title', 'M1')->sole();
        $lessonId = $module->lessons()->sole()->id;

        (new FakeCourseSeeder([['M1', 'now with standards', ['p-flag'], 7, 'optional']]))->run();

        $module->refresh();
        $this->assertSame(7, (int) $module->target_rung);
        $this->assertSame('now with standards', $module->summary);
        $this->assertTrue($module->lessons()->sole()->optional);
        $this->assertSame($lessonId, $module->lessons()->sole()->id); // updated, not replaced
    }

    public function test_dsa_reseed_is_enrollment_safe(): void
    {
        $this->page('array'); // one real DSA lesson so a completion can exist
        $this->seed(DsaCourseSeeder::class);

        $course = Course::where('slug', 'dsa')->sole();
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
        $this->complete($learner, 'array');

        $this->seed(DsaCourseSeeder::class); // the exact operation that used to wipe learner state

        $this->assertSame($course->id, Course::where('slug', 'dsa')->sole()->id);
        $this->assertSame(1, Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->count());
        $this->assertSame(1, LessonCompletion::where('user_id', $learner->id)->count());
        $this->assertSame(7, (int) $course->modules()->where('title', 'Coding Patterns')->sole()->target_rung);
    }
}
