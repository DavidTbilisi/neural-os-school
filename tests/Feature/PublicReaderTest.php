<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Page;
use App\Models\User;
use App\Services\Wiki\WikiRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicReaderTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, string $visibility, string $body = null): Page
    {
        return Page::create([
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'rel_path' => "x/{$slug}.md",
            'body_md' => $body ?? "# {$slug}",
            'visibility' => $visibility,
        ]);
    }

    public function test_library_lists_public_pages_only(): void
    {
        $this->page('alpha-page', Page::VISIBILITY_PUBLIC);
        $this->page('secret-page', Page::VISIBILITY_PRIVATE);

        $this->get('/library')
            ->assertOk()
            ->assertSee('Alpha Page')
            ->assertDontSee('Secret Page');
    }

    public function test_guest_can_read_a_published_page(): void
    {
        $this->page('open-page', Page::VISIBILITY_PUBLIC);

        $this->get('/wiki/open-page')->assertOk()->assertSee('Open Page');
    }

    public function test_private_page_is_not_found_for_guest(): void
    {
        $this->page('hidden-page', Page::VISIBILITY_PRIVATE);

        $this->get('/wiki/hidden-page')->assertNotFound();
    }

    public function test_staff_can_preview_a_private_page(): void
    {
        $this->page('draft-page', Page::VISIBILITY_PRIVATE);
        $staff = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($staff)->get('/wiki/draft-page')->assertOk()->assertSee('staff preview');
    }

    public function test_wiki_links_resolve_only_to_viewable_targets(): void
    {
        $this->page('target-b', Page::VISIBILITY_PUBLIC);
        $a = $this->page('page-a', Page::VISIBILITY_PUBLIC, "# Page A\n\n---\n\nSee [[target-b]] and [[missing-x|a ghost]].");

        $html = app(WikiRenderer::class)->render($a);

        $this->assertStringContainsString('href="/wiki/target-b"', $html);
        $this->assertStringNotContainsString('missing-x', $html);
        $this->assertStringContainsString('a ghost', $html); // ghost renders as plain text
    }
}
