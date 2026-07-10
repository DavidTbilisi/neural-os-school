<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Sign in');
    }

    public function test_dashboard_renders_with_stats_widget(): void
    {
        // Exercises WikiStatsOverview::getStats() against the (empty) schema.
        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    public function test_pages_resource_list_renders(): void
    {
        // Exercises PageResource::table() columns, filters and query.
        $this->actingAs($this->admin())->get('/admin/pages')->assertOk();
    }

    public function test_glossary_resource_list_renders(): void
    {
        $this->actingAs($this->admin())->get('/admin/glossary-terms')->assertOk();
    }

    public function test_users_resource_renders_for_admin(): void
    {
        $this->actingAs($this->admin())->get('/admin/users')->assertOk();
    }

    public function test_learner_cannot_access_admin_panel(): void
    {
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner)->get('/admin')->assertForbidden();
    }

    public function test_editor_can_access_admin_panel(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $this->actingAs($editor)->get('/admin')->assertOk();
    }

    public function test_new_registrations_default_to_learner(): void
    {
        $user = User::factory()->create();
        $this->assertSame(UserRole::Learner, $user->fresh()->role);
    }
}
