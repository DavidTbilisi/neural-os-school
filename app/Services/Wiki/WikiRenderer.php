<?php

namespace App\Services\Wiki;

use App\Models\Page;
use App\Support\Slug;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Renders a wiki page's markdown to safe HTML for the public reader:
 * strips the metadata preamble (shown separately) and semantic marks, resolves
 * [[wiki-links]] to /wiki/{slug} only when the target is viewable, then converts
 * GitHub-flavored markdown with raw HTML escaped.
 */
class WikiRenderer
{
    /** @var array<string,string>|null slug => title of viewable pages */
    private ?array $viewable = null;

    public function render(Page $page): string
    {
        $md = $this->stripPreamble($page->body_md);
        $md = $this->stripLeadingTitle($md, $page->title);
        $md = $this->stripMarks($md);
        $md = $this->linkify($md);

        return $this->toHtml($md);
    }

    /**
     * Drop the leading `# Title / **Summary** / **Sources** / **Last updated**`
     * block up to the first `---` — but only when that block really is the
     * standard preamble (it carries the `**Summary**` marker). Pages outside
     * the page format (e.g. the french-song units) open with real content, and
     * an early thematic break must not swallow it.
     */
    private function stripPreamble(string $body): string
    {
        if (preg_match('/\R---\R/', $body, $m, PREG_OFFSET_CAPTURE)
            && str_contains(substr($body, 0, $m[0][1]), '**Summary**')) {
            return substr($body, $m[0][1] + strlen($m[0][0]));
        }

        return $body;
    }

    /**
     * Drop a leading H1 that repeats the page title — the reader's header
     * already shows it. This is how non-preamble pages avoid a double title
     * (their H1 is where the title was parsed from in the first place).
     */
    private function stripLeadingTitle(string $md, string $title): string
    {
        if (preg_match('/^\s*#\s+(.+?)\s*(\R|$)/', $md, $m) && trim($m[1]) === trim($title)) {
            return substr($md, strlen($m[0]));
        }

        return $md;
    }

    /** `{{Sigil|content}}` → `content` (the reader doesn't surface sigils yet). */
    private function stripMarks(string $md): string
    {
        return preg_replace('/\{\{[^|{}]+\|(.*?)\}\}/s', '$1', $md);
    }

    /** @return array<string,string> */
    private function viewableSlugs(): array
    {
        return $this->viewable ??= Page::viewable()->pluck('title', 'slug')->all();
    }

    private function linkify(string $md): string
    {
        $viewable = $this->viewableSlugs();

        return preg_replace_callback('/\[\[(.+?)\]\]/', function (array $m) use ($viewable) {
            $inner = str_replace(['\\|', '\\#'], ['|', '#'], trim($m[1]));

            $display = null;
            if (str_contains($inner, '|')) {
                [$inner, $display] = explode('|', $inner, 2);
            }
            if (str_contains($inner, '#')) {
                [$inner] = explode('#', $inner, 2);
            }

            $slug = Slug::make($inner);

            if (array_key_exists($slug, $viewable)) {
                $text = $display !== null ? trim($display) : $viewable[$slug];

                return '['.$this->escapeLinkText($text).'](/wiki/'.$slug.')';
            }

            // Unpublished or non-existent target → plain text, no link.
            return $display !== null ? trim($display) : trim($inner);
        }, $md);
    }

    private function escapeLinkText(string $text): string
    {
        return str_replace(['[', ']'], ['\\[', '\\]'], $text);
    }

    private function toHtml(string $md): string
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        // Upgrade ```chart / ```mermaid fences to live diagrams; higher priority
        // than the core fenced-code renderer so it wins for those info-strings.
        $environment->addRenderer(FencedCode::class, new WikiFencedCodeRenderer(), 10);

        return (string) (new MarkdownConverter($environment))->convert($md);
    }
}
