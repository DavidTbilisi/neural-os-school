<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Drawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SketchpadTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $slug, string $status = Course::STATUS_PUBLISHED): Course
    {
        return Course::create(['slug' => $slug, 'title' => ucwords($slug), 'status' => $status]);
    }

    private const SCENE = '{"type":"excalidraw","version":2,"source":"local","elements":[{"id":"marker-xyz","type":"rectangle"}],"appState":{},"files":{}}';

    public function test_guest_is_redirected_to_login(): void
    {
        $this->course('sketch-course');

        $this->get('/courses/sketch-course/sketchpad')->assertRedirect(route('login'));
    }

    public function test_learner_can_open_the_sketchpad_for_a_published_course(): void
    {
        $this->course('open-course');
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $this->actingAs($learner)->get('/courses/open-course/sketchpad')
            ->assertOk()
            ->assertSee('sketchpad-root')
            ->assertSee(route('courses.drawing.save', 'open-course'));
    }

    public function test_draft_sketchpad_404s_for_learner_but_previews_for_staff(): void
    {
        $this->course('draft-course', Course::STATUS_DRAFT);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner)->get('/courses/draft-course/sketchpad')->assertNotFound();

        $staff = User::factory()->create(['role' => UserRole::Editor]);
        $this->actingAs($staff)->get('/courses/draft-course/sketchpad')->assertOk();
    }

    public function test_saving_creates_then_updates_a_single_drawing(): void
    {
        $course = $this->course('save-course');
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner);

        $this->postJson('/courses/save-course/drawing', ['scene' => self::SCENE])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseCount('drawings', 1);
        $this->assertSame(self::SCENE, Drawing::first()->scene);

        // Second save upserts the same (user, course) row — no duplicate.
        $updated = str_replace('marker-xyz', 'marker-2', self::SCENE);
        $this->postJson('/courses/save-course/drawing', ['scene' => $updated])->assertOk();

        $this->assertDatabaseCount('drawings', 1);
        $this->assertSame($updated, Drawing::first()->scene);
    }

    public function test_invalid_scene_is_rejected(): void
    {
        $this->course('bad-course');
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $this->actingAs($learner)
            ->postJson('/courses/bad-course/drawing', ['scene' => 'not-json{'])
            ->assertStatus(422);
    }

    public function test_a_learner_only_sees_their_own_scene(): void
    {
        $course = $this->course('mine-course');
        $alice = User::factory()->create(['role' => UserRole::Learner]);
        $bob = User::factory()->create(['role' => UserRole::Learner]);

        $this->actingAs($alice)->postJson('/courses/mine-course/drawing', ['scene' => self::SCENE])->assertOk();

        // Alice sees her marker embedded; Bob gets a blank canvas.
        $this->actingAs($alice)->get('/courses/mine-course/sketchpad')->assertOk()->assertSee('marker-xyz');
        $this->actingAs($bob)->get('/courses/mine-course/sketchpad')->assertOk()->assertDontSee('marker-xyz');

        $this->assertDatabaseCount('drawings', 1);
    }
}
