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

    public function test_leading_h1_matching_the_title_is_not_rendered_twice(): void
    {
        $page = $this->page('song-unit', Page::VISIBILITY_PUBLIC,
            "# Song Unit\n\n**Curriculum:** A1.\n\nLyrics here.");

        $html = app(WikiRenderer::class)->render($page);

        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringContainsString('Lyrics here.', $html);
    }

    public function test_early_thematic_break_does_not_swallow_content_without_a_summary_preamble(): void
    {
        $page = $this->page('rules-page', Page::VISIBILITY_PUBLIC,
            "# Rules Page\n\nThe intro paragraph.\n\n---\n\n## First section");

        $html = app(WikiRenderer::class)->render($page);

        $this->assertStringContainsString('The intro paragraph.', $html);
        $this->assertStringContainsString('First section', $html);
    }

    public function test_standard_summary_preamble_is_still_stripped(): void
    {
        $page = $this->page('formatted-page', Page::VISIBILITY_PUBLIC,
            "# Formatted Page\n\n**Summary**: One line.\n\n**Sources**: none.\n\n---\n\nReal body.");

        $html = app(WikiRenderer::class)->render($page);

        $this->assertStringNotContainsString('One line.', $html);
        $this->assertStringContainsString('Real body.', $html);
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

    public function test_wiki_links_are_not_resolved_inside_code(): void
    {
        $this->page('target-b', Page::VISIBILITY_PUBLIC);
        $body = "# Page A\n\n---\n\nProse links to [[target-b]].\n\n"
            ."Inline `[[ -f \$f ]]` stays whole.\n\n"
            ."```bash\nif [[ -f \"\$f\" ]]; then echo [[target-b]]; fi\n```\n";
        $a = $this->page('page-a', Page::VISIBILITY_PUBLIC, $body);

        $html = app(WikiRenderer::class)->render($a);

        // The prose link resolved exactly once; the two in code did not.
        $this->assertSame(1, substr_count($html, 'href="/wiki/target-b"'));
        $this->assertStringContainsString('[[ -f $f ]]', $html);
        $this->assertStringContainsString('[[target-b]]', $html);   // survives inside the fence
    }

    public function test_link_extraction_ignores_code_samples(): void
    {
        $parsed = app(\App\Services\Wiki\WikiParser::class)->parse(
            "# T\n\nSee [[real-target]].\n\n```bash\n[[ -f \"\$f\" ]] && cp [[not-a-link]] /tmp\n```\n",
            'x/t.md',
        );

        $this->assertSame(['real-target'], array_column($parsed['links'], 'target_slug'));
    }
}
