<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NavPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_get_the_public_items_in_declaration_order(): void
    {
        $this->assertSame(
            ['library', 'courses', 'gyms'],
            array_column(Navigation::for(null), 'key'),
        );
    }

    public function test_learners_get_the_signed_in_items_and_staff_get_admin(): void
    {
        $learner = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertSame(
            ['library', 'courses', 'gyms', 'my-courses', 'dashboard'],
            array_column(Navigation::for($learner), 'key'),
        );

        $this->assertContains('admin', array_column(Navigation::for($admin), 'key'));
    }

    public function test_a_saved_arrangement_reorders_and_hides(): void
    {
        $user = User::factory()->create();

        Navigation::save($user, ['gyms', 'library'], ['courses']);

        $this->assertSame(
            ['gyms', 'library', 'my-courses', 'dashboard'],
            array_column(Navigation::for($user->fresh()), 'key'),
        );
    }

    public function test_unsaved_items_keep_their_place_at_the_end(): void
    {
        $user = User::factory()->create();

        Navigation::save($user, ['dashboard'], []);

        $this->assertSame(
            ['dashboard', 'library', 'courses', 'gyms', 'my-courses'],
            array_column(Navigation::rowsFor($user->fresh()), 'key'),
        );
    }

    public function test_locked_items_cannot_be_hidden(): void
    {
        $user = User::factory()->create();

        Navigation::save($user, [], ['dashboard']);

        $this->assertContains('dashboard', array_column(Navigation::for($user->fresh()), 'key'));
        $this->assertSame([], $user->fresh()->nav_preferences['hidden']);
    }

    public function test_keys_a_learner_may_not_see_are_scrubbed_on_save(): void
    {
        $user = User::factory()->create();

        Navigation::save($user, ['admin', 'nonsense', 'gyms'], ['admin']);

        $preferences = $user->fresh()->nav_preferences;

        $this->assertNotContains('admin', $preferences['order']);
        $this->assertNotContains('nonsense', $preferences['order']);
        $this->assertSame([], $preferences['hidden']);
    }

    public function test_the_bar_renders_the_learners_arrangement(): void
    {
        $user = User::factory()->create();
        Navigation::save($user, ['gyms', 'library', 'courses'], ['courses']);

        $response = $this->actingAs($user)->get('/library');

        $response->assertOk()->assertDontSee('>Courses<', false);
        $this->assertLessThan(
            strpos($response->getContent(), '>Library<'),
            strpos($response->getContent(), '>Gyms<'),
            'Gyms was moved ahead of Library but did not render first.',
        );
    }

    public function test_the_form_saves_an_arrangement(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.nav-preferences-form')
            ->call('moveUp', 'gyms')
            ->set('visible', ['library', 'gyms', 'my-courses', 'dashboard'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            ['library', 'gyms', 'my-courses', 'dashboard'],
            array_column(Navigation::for($user->fresh()), 'key'),
        );
    }

    public function test_the_form_restores_defaults(): void
    {
        $user = User::factory()->create();
        Navigation::save($user, ['gyms'], ['library']);

        $this->actingAs($user);

        Volt::test('profile.nav-preferences-form')->call('restoreDefaults');

        $this->assertNull($user->fresh()->nav_preferences);
    }

    public function test_the_profile_page_shows_the_form(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/profile');

        $response->assertOk()->assertSeeVolt('profile.nav-preferences-form');
    }
}
