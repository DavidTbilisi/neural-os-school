<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\PlayGym;
use App\Models\Course;
use App\Models\Gym;
use App\Models\GymAttempt;
use App\Models\GymItem;
use App\Models\GymSession;
use App\Models\Lesson;
use App\Models\Page;
use App\Models\User;
use App\Support\KnowledgeLadder;
use Database\Seeders\DsaCourseSeeder;
use Database\Seeders\GymSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GymTest extends TestCase
{
    use RefreshDatabase;

    /** All items share choices [A,B] with A correct, so the flow is shuffle-independent. */
    private function gym(string $slug, string $status = Gym::STATUS_PUBLISHED, int $rounds = 3): Gym
    {
        $gym = Gym::create([
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'mode' => 'recognition',
            'target_reflex' => 'Name the pattern fast.',
            'timer_seconds' => 8,
            'round_count' => $rounds,
            'latency_target_ms' => 6000,
            'pass_accuracy' => 0.80,
            'promote_accuracy' => 0.85,
            'status' => $status,
            'stages' => [
                ['code' => 'S3', 'label' => 'Recognizer', 'min_accuracy' => 0.80, 'max_latency_ms' => null],
                ['code' => 'S2', 'label' => 'Builder', 'min_accuracy' => 0.60, 'max_latency_ms' => null],
                ['code' => 'S1', 'label' => 'Prototype', 'min_accuracy' => 0.0, 'max_latency_ms' => null],
            ],
        ]);

        for ($i = 0; $i < $rounds; $i++) {
            $gym->items()->create([
                'prompt' => "Question {$i}",
                'choices' => ['A', 'B'],
                'correct' => 'A',
                'explanation' => 'Because A.',
                'detail' => 'B is the near miss.',
                'sort' => $i,
            ]);
        }

        return $gym;
    }

    // ---- index / visibility ----------------------------------------------

    public function test_index_lists_published_gyms_only(): void
    {
        $this->gym('shown-gym', Gym::STATUS_PUBLISHED);
        $this->gym('hidden-gym', Gym::STATUS_DRAFT);

        $this->get('/gyms')->assertOk()->assertSee('Shown Gym')->assertDontSee('Hidden Gym');
    }

    public function test_playing_requires_auth(): void
    {
        $this->gym('locked-gym');
        $this->get('/gyms/locked-gym')->assertRedirect(route('login'));
    }

    public function test_draft_gym_404s_for_learner_but_previews_for_staff(): void
    {
        $this->gym('draft-gym', Gym::STATUS_DRAFT);

        $this->actingAs(User::factory()->create(['role' => UserRole::Learner]))
            ->get('/gyms/draft-gym')->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))
            ->get('/gyms/draft-gym')->assertOk();
    }

    // ---- session flow + telemetry ----------------------------------------

    public function test_full_session_logs_attempts_and_finalizes_summary(): void
    {
        $gym = $this->gym('flow-gym', rounds: 3);
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        Livewire::actingAs($learner)->test(PlayGym::class, ['slug' => 'flow-gym'])
            ->assertSet('phase', 'intro')
            ->call('start')->assertSet('phase', 'prompt')
            ->call('answer', 'A', 1200)->assertSet('phase', 'feedback')   // correct
            ->call('next')->assertSet('phase', 'prompt')
            ->call('answer', 'B', 2000)->assertSet('phase', 'feedback')   // wrong
            ->call('next')
            ->call('answer', null, 8000)->assertSet('phase', 'feedback')  // timeout
            ->call('next')->assertSet('phase', 'summary');

        // One session, finalized.
        $session = GymSession::where('user_id', $learner->id)->firstOrFail();
        $this->assertNotNull($session->completed_at);
        $this->assertSame(3, $session->total);
        $this->assertSame(1, $session->correct);
        $this->assertEqualsWithDelta(1 / 3, $session->accuracy, 0.001);
        $this->assertSame(2000, $session->median_latency_ms);  // median of [1200,2000,8000]
        $this->assertSame('L0', $session->stage_code);         // 33% → below chance → Unknown

        // Three attempts logged, including the null (timeout) selection.
        $attempts = GymAttempt::where('gym_session_id', $session->id)->get();
        $this->assertCount(3, $attempts);
        $this->assertSame(1, $attempts->where('is_correct', true)->count());
        $this->assertTrue($attempts->contains(fn ($a) => $a->selected === null && ! $a->is_correct));
    }

    public function test_answer_is_ignored_outside_the_prompt_phase(): void
    {
        $this->gym('guard-gym', rounds: 2);
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        // Answering before start() does nothing (no session, still intro).
        Livewire::actingAs($learner)->test(PlayGym::class, ['slug' => 'guard-gym'])
            ->call('answer', 'A', 1000)
            ->assertSet('phase', 'intro');

        $this->assertSame(0, GymAttempt::count());
    }

    public function test_start_is_idempotent_within_a_running_session(): void
    {
        $this->gym('once-gym', rounds: 3);
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        // A double-click on Start must not spawn a second (abandoned) session.
        Livewire::actingAs($learner)->test(PlayGym::class, ['slug' => 'once-gym'])
            ->call('start')
            ->call('start');

        $this->assertSame(1, GymSession::where('user_id', $learner->id)->count());
    }

    public function test_a_perfect_fast_session_reads_top_stage(): void
    {
        $gym = $this->gym('ace-gym', rounds: 2);
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        Livewire::actingAs($learner)->test(PlayGym::class, ['slug' => 'ace-gym'])
            ->call('start')
            ->call('answer', 'A', 900)->call('next')
            ->call('answer', 'A', 1100)->call('next')
            ->assertSet('phase', 'summary');

        $session = GymSession::where('user_id', $learner->id)->firstOrFail();
        $this->assertEqualsWithDelta(1.0, $session->accuracy, 0.001);
        $this->assertSame('L7', $session->stage_code); // 100% + fast → Reflexive (the gym ceiling)
    }

    // ---- blind-spot floor -------------------------------------------------

    /**
     * A gym whose items span several categories: $spec maps category => item
     * count. Every item offers [own category, 'WRONG'], so a run can be driven
     * by intent regardless of the shuffled order.
     *
     * @param  array<string, int>  $spec
     */
    private function categoryGym(string $slug, array $spec, bool $floor = true): Gym
    {
        $gym = Gym::create([
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'mode' => 'recognition',
            'round_count' => array_sum($spec),
            'timer_seconds' => 8,
            'latency_target_ms' => 6000,
            'pass_accuracy' => 0.80,
            'promote_accuracy' => 0.85,
            'blind_spot_floor' => $floor,
            'status' => Gym::STATUS_PUBLISHED,
        ]);

        $sort = 0;
        foreach ($spec as $category => $count) {
            for ($i = 0; $i < $count; $i++) {
                $gym->items()->create([
                    'prompt' => "{$category} #{$i}",
                    'choices' => [$category, 'WRONG'],
                    'correct' => $category,
                    'sort' => $sort++,
                ]);
            }
        }

        return $gym;
    }

    /** Ten 2-item categories — 20 rounds, so missing one whole category is still 90%. */
    private function twelvePatternSpec(): array
    {
        return array_fill_keys([
            'Hashmap', 'Two Pointers', 'Sliding Window', 'Binary Search', 'BFS',
            'DFS', 'Heap', 'Monotonic Stack', 'DP 1D', 'Backtracking',
        ], 2);
    }

    /**
     * Play a whole session, missing exactly the items $miss selects.
     * Returns the finalized session.
     */
    private function play(User $learner, Gym $gym, callable $miss, int $latencyMs = 1000): GymSession
    {
        $test = Livewire::actingAs($learner)->test(PlayGym::class, ['slug' => $gym->slug])->call('start');

        foreach ($gym->items as $ignored) {
            $item = $test->instance()->currentItem();
            $this->assertNotNull($item, 'ran out of items before the summary');
            $test->call('answer', $miss($item) ? 'WRONG' : $item->correct, $latencyMs)->call('next');
        }

        $test->assertSet('phase', 'summary');

        return GymSession::where('user_id', $learner->id)->latest('id')->firstOrFail();
    }

    public function test_a_zeroed_category_caps_the_rung_and_names_the_blind_spot(): void
    {
        $gym = $this->categoryGym('floor-gym', $this->twelvePatternSpec());
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        // 18/20 = 90%, fast — but both Monotonic Stack items missed.
        $session = $this->play($learner, $gym, fn (GymItem $i) => $i->correct === 'Monotonic Stack');

        $this->assertEqualsWithDelta(0.90, $session->accuracy, 0.001);
        $this->assertSame([['category' => 'Monotonic Stack', 'items' => 2]], $session->blind_spots);

        // Accuracy + speed alone read Reflexive; the floor withholds it.
        $this->assertSame(7, KnowledgeLadder::levelForGym($gym, 0.90, 1000));
        $this->assertSame('L'.KnowledgeLadder::BLIND_SPOT_CEILING, $session->stage_code);
        $this->assertSame('L4', $session->stage_code);
    }

    public function test_the_same_accuracy_spread_across_categories_still_reaches_the_top_rung(): void
    {
        $gym = $this->categoryGym('spread-gym', $this->twelvePatternSpec());
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        // Same 18/20 at the same speed — but the two misses land in different
        // categories, so neither is zeroed. Distribution, not the mean, decides.
        $session = $this->play(
            $learner,
            $gym,
            fn (GymItem $i) => in_array($i->prompt, ['Monotonic Stack #0', 'Heap #0'], true),
        );

        $this->assertEqualsWithDelta(0.90, $session->accuracy, 0.001);
        $this->assertSame([], $session->blind_spots);
        $this->assertSame('L7', $session->stage_code);
    }

    public function test_the_summary_names_the_blind_spot_and_the_rung_it_cost(): void
    {
        $gym = $this->categoryGym('report-gym', $this->twelvePatternSpec());
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $test = Livewire::actingAs($learner)->test(PlayGym::class, ['slug' => 'report-gym'])->call('start');
        foreach ($gym->items as $ignored) {
            $item = $test->instance()->currentItem();
            $test->call('answer', $item->correct === 'Monotonic Stack' ? 'WRONG' : $item->correct, 1000)->call('next');
        }

        $test->assertSee('Blind spot')
            ->assertSee('Monotonic Stack')
            ->assertSee('0 of 2 items in this run')
            ->assertSee('Reflexive')          // what accuracy + speed alone read
            ->assertDontSee('reflex is stabilizing');
    }

    public function test_the_floor_is_per_gym_and_can_be_switched_off(): void
    {
        $gym = $this->categoryGym('nofloor-gym', $this->twelvePatternSpec(), floor: false);
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $session = $this->play($learner, $gym, fn (GymItem $i) => $i->correct === 'Monotonic Stack');

        // Identical run to the floored gym — this one still promotes.
        $this->assertEqualsWithDelta(0.90, $session->accuracy, 0.001);
        $this->assertSame([], $session->blind_spots);
        $this->assertSame('L7', $session->stage_code);
    }

    public function test_the_floor_only_lowers_a_rung_never_raises_one(): void
    {
        $gym = $this->categoryGym('cap-gym', ['A' => 1]);

        // Bands at or below the ceiling are untouched by a blind spot.
        // (Pairs, not float keys — PHP truncates those to int and collides them.)
        foreach ([[0.40, 0], [0.55, 1], [0.75, 3], [0.82, 4]] as [$accuracy, $expected]) {
            $this->assertSame($expected, KnowledgeLadder::levelForGym($gym, $accuracy, 1000, true));
            $this->assertSame($expected, KnowledgeLadder::levelForGym($gym, $accuracy, 1000));
        }

        // Only the promote band (5 Operational, 7 Reflexive) is withheld.
        $this->assertSame(5, KnowledgeLadder::levelForGym($gym, 0.95, 9000));
        $this->assertSame(4, KnowledgeLadder::levelForGym($gym, 0.95, 9000, true));
        $this->assertSame(7, KnowledgeLadder::levelForGym($gym, 0.95, 1000));
        $this->assertSame(4, KnowledgeLadder::levelForGym($gym, 0.95, 1000, true));
    }

    public function test_seeded_algorithm_pattern_gym_declares_the_floor(): void
    {
        $this->seed(DsaCourseSeeder::class);
        $this->seed(GymSeeder::class);

        $gym = Gym::where('slug', 'algorithm-pattern-gym')->firstOrFail();
        $this->assertTrue($gym->blind_spot_floor);

        // Every deck item's `correct` is a pattern family — the categories the
        // floor groups by. Monotonic Stack is the family the wiki run zeroed.
        $families = $gym->items()->pluck('correct')->unique();
        $this->assertTrue($families->contains('Monotonic Stack'));
        $this->assertSame(2, $gym->items()->where('correct', 'Monotonic Stack')->count());
    }

    // ---- course link ------------------------------------------------------

    public function test_course_page_shows_a_practice_link_to_its_gym(): void
    {
        $course = Course::create(['slug' => 'linked-course', 'title' => 'Linked Course', 'status' => Course::STATUS_PUBLISHED]);
        $gym = $this->gym('linked-gym');
        $gym->update(['course_id' => $course->id]);

        $this->get('/courses/linked-course')
            ->assertOk()
            ->assertSee('Practice')
            ->assertSee(route('gyms.play', 'linked-gym'));
    }

    // ---- module tagging (per-module evidence) ------------------------------

    public function test_gym_items_can_be_tagged_with_a_module(): void
    {
        $course = Course::create(['slug' => 'tag-course', 'title' => 'Tag Course', 'status' => Course::STATUS_PUBLISHED]);
        $module = $course->modules()->create(['title' => 'Module A', 'sort' => 0]);

        $gym = $this->gym('tag-gym', rounds: 1);
        $item = $gym->items->first();
        $item->update(['module_id' => $module->id]);

        $this->assertTrue($item->fresh()->module->is($module));
        $this->assertTrue($module->gymItems->contains($item));
    }

    public function test_module_gym_attempts_scopes_attempts_to_tagged_items(): void
    {
        $course = Course::create(['slug' => 'scope-course', 'title' => 'Scope Course', 'status' => Course::STATUS_PUBLISHED]);
        $moduleA = $course->modules()->create(['title' => 'Module A', 'sort' => 0]);
        $moduleB = $course->modules()->create(['title' => 'Module B', 'sort' => 1]);

        $gym = $this->gym('scope-gym', rounds: 2);
        [$itemA, $itemB] = $gym->items;
        $itemA->update(['module_id' => $moduleA->id]);
        $itemB->update(['module_id' => $moduleB->id]);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $other = User::factory()->create(['role' => UserRole::Learner]);

        foreach ([$learner, $other] as $user) {
            $session = GymSession::create(['user_id' => $user->id, 'gym_id' => $gym->id]);
            GymAttempt::create(['gym_session_id' => $session->id, 'gym_item_id' => $itemA->id, 'selected' => 'A', 'is_correct' => true, 'latency_ms' => 1000]);
            GymAttempt::create(['gym_session_id' => $session->id, 'gym_item_id' => $itemB->id, 'selected' => 'B', 'is_correct' => false, 'latency_ms' => 2000]);
        }

        // The module sees only attempts on its own items, across learners…
        $this->assertSame(2, $moduleA->gymAttempts()->count());
        $this->assertTrue($moduleA->gymAttempts->every(fn ($a) => $a->gym_item_id === $itemA->id));

        // …and narrows to one learner via the session (the per-learner evidence stream).
        $mine = $moduleA->gymAttempts()
            ->whereHas('session', fn ($q) => $q->where('user_id', $learner->id))
            ->get();
        $this->assertCount(1, $mine);
        $this->assertTrue($mine->first()->is_correct);
    }

    public function test_deleting_a_module_untags_items_without_deleting_them(): void
    {
        $course = Course::create(['slug' => 'churn-course', 'title' => 'Churn Course', 'status' => Course::STATUS_PUBLISHED]);
        $module = $course->modules()->create(['title' => 'Module A', 'sort' => 0]);

        $gym = $this->gym('churn-gym', rounds: 1);
        $item = $gym->items->first();
        $item->update(['module_id' => $module->id]);

        // Course re-seeds delete modules; the item must survive, merely untagged.
        $module->delete();

        $this->assertNull($item->fresh()->module_id);
        $this->assertSame(1, $gym->items()->count());
    }

    public function test_gym_seeder_tags_items_to_dsa_modules(): void
    {
        $this->seed(DsaCourseSeeder::class);
        $this->seed(GymSeeder::class);

        $gym = Gym::where('slug', 'algorithm-pattern-gym')->firstOrFail();
        $dsaModuleIds = Course::where('slug', 'dsa')->firstOrFail()->modules->pluck('id');

        $this->assertSame(20, $gym->items()->count());
        $this->assertSame(0, $gym->items()->whereNull('module_id')->count());
        $this->assertTrue($gym->items->pluck('module_id')->diff($dsaModuleIds)->isEmpty());
        $this->assertSame(
            'Hashing, Heaps & Caches',
            $gym->items()->where('sort', 0)->first()->module->title,
        );

        // Course seeders upsert modules in place, so a course re-seed leaves
        // item IDs AND module tags untouched — no re-tagging needed.
        $itemIdsBefore = $gym->items()->orderBy('sort')->pluck('id');
        $moduleIdsBefore = $gym->items()->orderBy('sort')->pluck('module_id');
        $this->seed(DsaCourseSeeder::class);
        $this->assertSame(0, GymItem::whereNull('module_id')->count());
        $this->assertEquals($itemIdsBefore, $gym->items()->orderBy('sort')->pluck('id'));
        $this->assertEquals($moduleIdsBefore, $gym->items()->orderBy('sort')->pluck('module_id'));
    }

    // ---- lesson tagging (lesson-embedded checks) ---------------------------

    private function lessonFor(string $slug): Lesson
    {
        $course = Course::create(['slug' => "{$slug}-course", 'title' => 'Lesson Course', 'status' => Course::STATUS_PUBLISHED]);
        $module = $course->modules()->create(['title' => 'Module A', 'sort' => 0]);
        $page = Page::create(['slug' => $slug, 'title' => ucwords($slug), 'rel_path' => "x/{$slug}.md", 'body_md' => "# {$slug}"]);

        return Lesson::create(['module_id' => $module->id, 'page_id' => $page->id, 'title' => ucwords($slug), 'sort' => 0]);
    }

    public function test_gym_items_can_be_tagged_with_a_lesson_in_addition_to_a_module(): void
    {
        $lesson = $this->lessonFor('tagged-lesson');

        $gym = $this->gym('lesson-tag-gym', rounds: 1);
        $item = $gym->items->first();
        $item->update(['module_id' => $lesson->module_id, 'lesson_id' => $lesson->id]);

        $this->assertTrue($item->fresh()->lesson->is($lesson));
        $this->assertTrue($lesson->gymItems->contains($item));
        $this->assertTrue($item->fresh()->module->is($lesson->module));
    }

    /** Every lesson-tagged item's module_id must match its lesson's parent module — the invariant Report::moduleEvidence() relies on. */
    public function test_lesson_check_items_module_id_matches_their_lessons_parent_module(): void
    {
        $lesson = $this->lessonFor('invariant-lesson');

        $gym = $this->gym('invariant-gym', rounds: 1);
        $item = $gym->items->first();
        $item->update(['module_id' => $lesson->module_id, 'lesson_id' => $lesson->id]);

        $tagged = GymItem::whereNotNull('lesson_id')->get();
        foreach ($tagged as $taggedItem) {
            $this->assertSame(
                $taggedItem->lesson->module_id,
                $taggedItem->module_id,
                "GymItem {$taggedItem->id} module_id must match its lesson's parent module",
            );
        }
    }

    public function test_deleting_a_lesson_untags_its_check_items_without_deleting_them(): void
    {
        $lesson = $this->lessonFor('doomed-lesson');

        $gym = $this->gym('doomed-gym', rounds: 1);
        $item = $gym->items->first();
        $item->update(['module_id' => $lesson->module_id, 'lesson_id' => $lesson->id]);

        $lesson->delete();

        $this->assertNull($item->fresh()->lesson_id);
        $this->assertNotNull($item->fresh()->module_id); // module tag (coverage rollup) survives independently
        $this->assertSame(1, $gym->items()->count());
    }
}
