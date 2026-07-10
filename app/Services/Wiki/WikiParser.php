<?php

namespace App\Services\Wiki;

use App\Support\Slug;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses one wiki markdown file into a structured array. Regexes mirror the
 * Python conventions copied across tools/para_indexer.py, wiki_link_graph.py,
 * wiki_page_catalog.py so the resulting data matches the wiki's own indexers.
 */
class WikiParser
{
    public function parse(string $content, string $relPath): array
    {
        [$frontmatter, $body] = $this->splitFrontmatter($content);

        return [
            'frontmatter' => $frontmatter,
            'rel_path' => $relPath,
            'title' => $this->title($body) ?? $this->title($content) ?? Slug::make(basename($relPath)),
            'summary' => $this->firstMatch('/^\*\*Summary\*\*:\s*(.+?)\s*$/m', $body),
            'sources' => $this->sources($body),
            'last_updated' => $this->firstMatch('/(?:\*\*)?Last updated(?:\*\*)?:\s*(\d{4}-\d{2}-\d{2})/', $content),
            'body' => $body,
            'word_count' => preg_match_all('/\S+/', $body),
            'links' => $this->links($body),
            'marks' => $this->marks($frontmatter),
        ];
    }

    /** @return array{0: array<string,mixed>, 1: string} [frontmatter, body] */
    private function splitFrontmatter(string $content): array
    {
        if (preg_match('/^---\R(.*?)\R---\R?/s', $content, $m)) {
            $body = substr($content, strlen($m[0]));
            return [$this->parseFrontmatter($m[1]), $body];
        }

        return [[], $content];
    }

    /**
     * Prefer a real YAML parse (handles nested lists like semantic_tags); fall
     * back to a flat line-based key:value scan for the ~19 malformed blocks that
     * the Python line-scanner tolerated but strict YAML rejects.
     */
    private function parseFrontmatter(string $raw): array
    {
        try {
            $data = Yaml::parse($raw);
            if (is_array($data)) {
                return $data;
            }
        } catch (ParseException) {
            // fall through to the tolerant scanner
        }

        $out = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_-]*):\s*(.*)$/', $line, $mm)) {
                $out[$mm[1]] = trim($mm[2], " \t\"'");
            }
        }

        return $out;
    }

    private function title(string $text): ?string
    {
        return $this->firstMatch('/^#\s+(.+)$/m', $text);
    }

    private function firstMatch(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $m) ? trim($m[1]) : null;
    }

    /** Best-effort capture of the **Sources** block. */
    private function sources(string $body): ?string
    {
        if (preg_match('/\*\*Sources\*\*:\s*(.*?)(?=\R\*\*Last updated|\R---|\R##\s|\z)/s', $body, $m)) {
            return trim($m[1]) ?: null;
        }

        return null;
    }

    /**
     * Extract [[wiki-links]] with optional #anchor and |display text, matching
     * WIKILINK_RE (escaped \| inside tables is tolerated).
     *
     * @return list<array{target_slug:string, display_text:?string, anchor:?string}>
     */
    private function links(string $body): array
    {
        if (! preg_match_all('/\[\[(.+?)\]\]/', $body, $matches)) {
            return [];
        }

        $links = [];
        foreach ($matches[1] as $inner) {
            $inner = str_replace(['\\|', '\\#'], ['|', '#'], trim($inner));

            $display = null;
            if (str_contains($inner, '|')) {
                [$inner, $display] = explode('|', $inner, 2);
            }

            $anchor = null;
            if (str_contains($inner, '#')) {
                [$inner, $anchor] = explode('#', $inner, 2);
            }

            $target = Slug::make($inner);
            if ($target === '') {
                continue;
            }

            $links[] = [
                'target_slug' => $target,
                'display_text' => $display !== null ? trim($display) : null,
                'anchor' => $anchor !== null ? trim($anchor) : null,
            ];
        }

        return $links;
    }

    /**
     * Semantic-reading marks come from the plugin-written `semantic_tags:`
     * frontmatter list of {tag, text, para}.
     *
     * @return list<array{sigil:string, text:string, para_index:?int}>
     */
    private function marks(array $frontmatter): array
    {
        $tags = $frontmatter['semantic_tags'] ?? null;
        if (! is_array($tags)) {
            return [];
        }

        $marks = [];
        foreach ($tags as $entry) {
            if (! is_array($entry) || ! isset($entry['tag'])) {
                continue;
            }

            $marks[] = [
                'sigil' => (string) $entry['tag'],
                'text' => (string) ($entry['text'] ?? ''),
                'para_index' => isset($entry['para']) ? (int) $entry['para'] : null,
            ];
        }

        return $marks;
    }
}
