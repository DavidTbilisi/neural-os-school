<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\PlayGym;
use App\Models\Course;
use App\Models\Gym;
use App\Models\GymAttempt;
use App\Models\GymSession;
use App\Models\User;
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
        $this->assertSame('S1', $session->stage_code);         // 33% → lowest stage

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
        $this->assertSame('S3', $session->stage_code); // 100% → top stage
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
}
