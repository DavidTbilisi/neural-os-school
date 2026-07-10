<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Widgets\CoverageHeatmap;
use App\Filament\Widgets\DomainScoresChart;
use App\Filament\Widgets\WikiStatsOverview;
use App\Models\Page;
use App\Models\ScoreLens;
use App\Models\User;
use Database\Seeders\WikiReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_dashboard_widgets_render_with_data(): void
    {
        (new WikiReferenceSeeder)->run();

        ScoreLens::create(['lens_type' => 'total', 'lens_key' => '__total__', 'pages' => 1000,
            'complexity' => 11136.4, 'acquirement' => 1207, 'absorbed' => 10.8]);
        ScoreLens::create(['lens_type' => 'domain', 'lens_key' => '10', 'lens_label' => 'Learning',
            'pages' => 700, 'complexity' => 5000, 'acquirement' => 500, 'absorbed' => 10]);

        Page::create(['slug' => 'p1', 'title' => 'P1', 'rel_path' => 'learning-systems/p1.md',
            'body_md' => '# P1', 'domain_id' => 10, 'palace' => 'core-memory', 'visibility' => 'public']);

        $this->actingAs($this->admin());

        // Widgets are lazy on the dashboard, so render them directly.
        Livewire::test(WikiStatsOverview::class)->assertOk()->assertSee('Complexity')->assertSee('Absorbed');
        Livewire::test(DomainScoresChart::class)->assertOk();
        Livewire::test(CoverageHeatmap::class)->assertOk()->assertSee('Coverage');
    }
}
