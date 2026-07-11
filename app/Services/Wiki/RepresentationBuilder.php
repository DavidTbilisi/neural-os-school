<?php

namespace App\Services\Wiki;

use App\Models\Link;
use App\Models\Page;

/**
 * Builds the ≥4 "canvas representations" for a wiki page — the visual companions
 * to the reading column (see resources/views/livewire/partials/representations).
 *
 * Mixed 2+2 quartet:
 *   analytical — concept graph (echarts force, from the `links` graph)
 *              — structure flow (mermaid, from the heading skeleton)
 *              — matrix         (the note's richest markdown table)
 *   mnemonic   — memory palace  (glyph grid: rooms → acronym → prose pegs → sections)
 *              — frozen scene    (curated `pages.scene_json`, else prose-mnemonic fallback)
 *
 * The analytical panels derive mechanically from any page. The frozen scene is
 * hand-authored in the Filament admin (the one non-markdown-canonical field).
 * This is the server-side twin of tools/illustrate/illustrate.py in the research repo.
 */
class RepresentationBuilder
{
    /** Categorical node palette, legible on both warm-paper and warm-charcoal. */
    private const DOMAIN_COLORS = [
        '#3A6B8A', '#5C7A45', '#B8842B', '#C0392B', '#6B5B95',
        '#4E8A8B', '#A0562B', '#7A7D45', '#8A5D9A', '#3E7A6B',
    ];
    private const CENTER_COLOR = '#8B2E3C'; // oxblood

    /** keyword => emoji, for palace/scene tiles. */
    private const GLYPHS = [
        'cut' => '✂️', 'bridge' => '🌉', 'vertex' => '🔴', 'edge' => '➖', 'path' => '🛤️',
        'redundan' => '♻️', 'route' => '🧭', 'block' => '🧱', 'cluster' => '🫧', 'tree' => '🌳',
        'connect' => '🔗', 'ear' => '👂', 'degree' => '📐', 'flow' => '🌊', 'verify' => '✅',
        'color' => '🎨', 'match' => '💞', 'cycle' => '🔄', 'planar' => '🗺️', 'random' => '🎲',
        'menger' => '🔀', 'network' => '🕸️', 'hamilton' => '🧵', 'ramsey' => '🎭',
    ];
    private const DEFAULT_GLYPHS = ['🔷', '🟦', '🟨', '🟩', '🟧', '🟥', '🟪', '⬜'];

    private const BOILERPLATE = [
        'related pages', 'meter', 'mnemonic', 'memory checksum', 'checksum', 'visual',
        'sources', 'summary', 'u — see (cast)', 'd — name (nedf)', 'f — do (spear)',
        'b — watch (heart)', 'l — predict (oracle)', 'r — act (grace)',
    ];

    /** @return array{panels: string[], graph: array, flow: ?array, matrix: ?array, palace: ?array, scene: ?array} */
    public function for(Page $page): array
    {
        $body = $this->stripCodeFences($page->body_md ?? '');

        $graph = $this->conceptGraph($page);
        $flow = $this->structureFlow($page, $body);
        $matrix = $this->matrix($page->body_md ?? '');
        $palace = $this->palace($body, $page->body_md ?? '');
        $scene = $this->scene($page, $page->body_md ?? '');

        $panels = ['graph', 'flow'];
        if ($matrix) {
            $panels[] = 'matrix';
        }
        if ($palace) {
            $panels[] = 'palace';
        }
        $panels[] = 'scene';

        return compact('panels', 'graph', 'flow', 'matrix', 'palace', 'scene');
    }

    // --- 1. concept graph (echarts force) ---------------------------------
    private function conceptGraph(Page $page, int $cap = 14): array
    {
        $neighbors = collect();

        Link::where('source_page_id', $page->id)->where('resolved', true)
            ->with('target:id,slug,title,domain_id,visibility')->get()
            ->each(fn (Link $l) => $l->target && $neighbors->put($l->target->id, $l->target));
        Link::where('target_page_id', $page->id)->where('resolved', true)
            ->with('source:id,slug,title,domain_id,visibility')->get()
            ->each(fn (Link $l) => $l->source && $neighbors->put($l->source->id, $l->source));

        $neighbors = $neighbors->reject(fn (Page $p) => $p->id === $page->id)
            ->filter(fn (Page $p) => in_array($p->visibility, [Page::VISIBILITY_PUBLIC, Page::VISIBILITY_UNLISTED], true))
            ->take($cap);

        $nodes = [[
            'id' => $page->slug,
            'name' => $page->title,
            'symbolSize' => 46,
            'itemStyle' => ['color' => self::CENTER_COLOR],
            'label' => ['fontWeight' => 'bold'],
        ]];
        $links = [];
        foreach ($neighbors as $n) {
            $nodes[] = [
                'id' => $n->slug,
                'name' => $n->title,
                'symbolSize' => 22,
                'itemStyle' => ['color' => $this->domainColor($n->domain_id)],
            ];
            $links[] = ['source' => $page->slug, 'target' => $n->slug];
        }

        return [
            'tooltip' => ['show' => true, 'formatter' => '{b}'],
            'series' => [[
                'type' => 'graph',
                'layout' => 'force',
                'roam' => true,
                'draggable' => true,
                'label' => ['show' => true, 'position' => 'right', 'fontSize' => 11, 'distance' => 4],
                // hideOverlap drops colliding labels so a dense neighbourhood stays legible.
                'labelLayout' => ['hideOverlap' => true],
                'force' => ['repulsion' => 520, 'edgeLength' => [120, 200], 'gravity' => 0.05, 'friction' => 0.12],
                'lineStyle' => ['color' => '#B0A489', 'opacity' => 0.55, 'width' => 1.2, 'curveness' => 0.06],
                'emphasis' => ['focus' => 'adjacency', 'label' => ['show' => true], 'lineStyle' => ['width' => 2.5]],
                'data' => $nodes,
                'links' => $links,
            ]],
        ];
    }

    private function domainColor(?int $domainId): string
    {
        $i = $domainId ? ($domainId - 1) : 0;

        return self::DOMAIN_COLORS[abs($i) % count(self::DOMAIN_COLORS)];
    }

    // --- 2. structure flow (mermaid) --------------------------------------
    private function structureFlow(Page $page, string $body): ?array
    {
        $tree = $this->parseHeadings($body);
        if (empty($tree)) {
            return null;
        }

        $lines = ['flowchart LR'];
        $root = 'n0';
        $lines[] = sprintf('  %s[%s]', $root, $this->mermaidLabel($page->title));
        $i = 0;
        foreach ($tree as $h2) {
            $id = 'n'.(++$i);
            $lines[] = sprintf('  %s --> %s[%s]', $root, $id, $this->mermaidLabel($h2['name']));
            foreach ($h2['children'] as $h3) {
                $cid = 'n'.(++$i);
                $lines[] = sprintf('  %s --> %s[%s]', $id, $cid, $this->mermaidLabel($h3['name']));
                if ($i > 40) {
                    break 2;
                }
            }
            if ($i > 40) {
                break;
            }
        }
        // Tint the root node (a `style` statement is more robust than class/classDef).
        $lines[] = sprintf('  style %s fill:#8B2E3C,stroke:#8B2E3C,color:#FBF8F1', $root);

        return ['mermaid' => implode("\n", $lines)];
    }

    private function mermaidLabel(string $s): string
    {
        $s = preg_replace('/[`*]/u', '', $s);
        $s = preg_replace('/\s+/u', ' ', trim($s));
        $s = mb_substr($s, 0, 40);
        // Quote to survive punctuation; escape embedded quotes.
        return '"'.str_replace('"', '&quot;', $s).'"';
    }

    // --- 2b. matrix (richest markdown table) ------------------------------
    private function matrix(string $raw): ?array
    {
        $lines = preg_split('/\R/u', $raw);
        $tables = [];
        $heading = '';
        $n = count($lines);
        for ($i = 0; $i < $n; $i++) {
            if (preg_match('/^#{2,3}\s+(.+)$/u', $lines[$i], $m)) {
                $heading = trim($m[1]);
            }
            $isRow = str_starts_with(ltrim($lines[$i]), '|');
            $sep = ($i + 1 < $n) && preg_match('/^\s*\|[-:\s|]+\|\s*$/u', $lines[$i + 1]);
            if ($isRow && $sep) {
                $cols = $this->splitRow($lines[$i]);
                $rows = [];
                $j = $i + 2;
                while ($j < $n && str_starts_with(ltrim($lines[$j]), '|')) {
                    $cells = $this->splitRow($lines[$j]);
                    if (count($cells) === count($cols)) {
                        $rows[] = array_map(fn ($c) => trim(preg_replace('/\*\*/u', '', $c)), $cells);
                    }
                    $j++;
                }
                if ($rows) {
                    $tables[] = ['heading' => $heading ?: 'Comparison', 'columns' => $cols, 'rows' => $rows];
                }
                $i = $j - 1;
            }
        }
        if (empty($tables)) {
            return null;
        }
        usort($tables, fn ($a, $b) => count($b['rows']) * count($b['columns']) <=> count($a['rows']) * count($a['columns']));

        return $tables[0];
    }

    private function splitRow(string $line): array
    {
        return array_map('trim', explode('|', trim(trim($line), '|')));
    }

    // --- 3. memory palace -------------------------------------------------
    private function palace(string $body, string $raw): ?array
    {
        // 3a) numbered rooms: "## 1. Register Room"
        $rooms = [];
        if (preg_match_all('/^##\s+(\d+)\.\s+(.+)$/um', $raw, $m, PREG_SET_ORDER)) {
            foreach ($m as $mm) {
                $rooms[] = trim($mm[2]);
            }
        }
        if (count($rooms) >= 3) {
            return ['kind' => 'rooms', 'title' => 'Memory palace', 'tiles' => $this->tiles($rooms, true)];
        }

        // 3b) acronym mnemonic: bulleted "- **C**ut vertices: …"
        $acr = $this->parseMnemonic($raw);
        if ($acr && count($acr['items']) >= 3) {
            $tiles = [];
            foreach ($acr['items'] as $i => $it) {
                $label = trim(explode(':', $it['phrase'])[0]);
                $tiles[] = ['badge' => $it['letter'], 'glyph' => $this->glyphFor($it['phrase'], $i),
                    'label' => mb_substr($label, 0, 40), 'note' => $it['phrase']];
            }

            return ['kind' => 'acronym', 'title' => $acr['acronym'] ?: 'Mnemonic', 'tiles' => $tiles];
        }

        // 3c) prose-mnemonic pegs: bold actors from Mnemonic + Participants
        $pegs = $this->boldTerms($this->sectionText($raw, 'Mnemonic')."\n".$this->sectionText($raw, 'Participants'));
        if (count($pegs) >= 3) {
            return ['kind' => 'pegs', 'title' => 'Mnemonic pegs', 'tiles' => $this->tiles($pegs, false, '◆')];
        }

        // 3d) section peg-board (last resort — guarantees the 4th panel)
        $tops = [];
        if (preg_match_all('/^##\s+(.+)$/um', $raw, $m)) {
            foreach ($m[1] as $h) {
                $clean = trim(preg_replace('/[*`]/u', '', $h));
                if (! in_array(mb_strtolower($clean), self::BOILERPLATE, true) && ! preg_match('/^\d+\./', $clean)) {
                    $tops[] = mb_substr($clean, 0, 40);
                }
            }
        }
        $tops = array_slice($tops, 0, 8);
        if (count($tops) >= 3) {
            return ['kind' => 'sections', 'title' => 'Section peg-board', 'tiles' => $this->tiles($tops, true)];
        }

        return null;
    }

    /** @param string[] $labels */
    private function tiles(array $labels, bool $numbered, string $badge = ''): array
    {
        $tiles = [];
        foreach ($labels as $i => $label) {
            $tiles[] = [
                'badge' => $numbered ? (string) ($i + 1) : $badge,
                'glyph' => $this->glyphFor($label, $i),
                'label' => $label,
                'note' => '',
            ];
        }

        return $tiles;
    }

    private function parseMnemonic(string $raw): ?array
    {
        $sec = $this->sectionText($raw, 'Mnemonic');
        if (trim($sec) === '') {
            return null;
        }
        $acronym = preg_match('/\*\*([A-Z][A-Z]{2,})\*\*/u', $sec, $am) ? $am[1] : '';
        $items = [];
        if (preg_match_all('/[-*]\s*\*\*([A-Za-z])\*\*([A-Za-z]*)[:\s]+(.+)/u', $sec, $m, PREG_SET_ORDER)) {
            foreach ($m as $mm) {
                $phrase = trim($mm[1].$mm[2].' '.$mm[3]);
                $items[] = ['letter' => mb_strtoupper($mm[1]), 'phrase' => mb_substr(preg_replace('/\s+/u', ' ', $phrase), 0, 90)];
            }
        }

        return $items ? ['acronym' => $acronym, 'items' => $items] : null;
    }

    // --- 4. frozen scene --------------------------------------------------
    private function scene(Page $page, string $raw): array
    {
        // Curated in the Filament admin — the authoritative source when present.
        $curated = $page->scene_json;
        if (is_array($curated) && ! empty($curated['title'])) {
            return [
                'title' => $curated['title'],
                'caption' => $curated['caption'] ?? '',
                'sequence' => (bool) ($curated['sequence'] ?? false),
                'elements' => array_map(fn ($e) => [
                    'glyph' => $e['glyph'] ?? '◆',
                    'label' => $e['label'] ?? '',
                ], $curated['elements'] ?? []),
            ];
        }

        // Auto fallback: the prose Mnemonic's single image + its named actors.
        $mnem = $this->sectionText($raw, 'Mnemonic');
        if (trim($mnem) !== '') {
            $prose = trim(preg_replace('/\s+/u', ' ', preg_replace('/[*`]/u', '', $mnem)));
            $caption = $prose !== '' ? explode('. ', $prose)[0].'.' : ($page->summary ?? '');
            $elements = [];
            foreach ($this->boldTerms($mnem) as $i => $p) {
                $elements[] = ['glyph' => $this->glyphFor($p, $i), 'label' => $p];
            }

            return ['title' => $page->title, 'caption' => mb_substr($caption, 0, 200), 'sequence' => false, 'elements' => $elements];
        }

        return ['title' => $page->title, 'caption' => mb_substr($page->summary ?? '', 0, 200), 'sequence' => false, 'elements' => []];
    }

    // --- shared parsing helpers ------------------------------------------
    private function stripCodeFences(string $s): string
    {
        return preg_replace('/```.*?```/us', '', $s) ?? $s;
    }

    /** @return array<int, array{name: string, children: array}> */
    private function parseHeadings(string $body): array
    {
        $tree = [];
        $cur = null;
        if (preg_match_all('/^(#{2,3})\s+(.+)$/um', $body, $m, PREG_SET_ORDER)) {
            foreach ($m as $mm) {
                $node = ['name' => trim($mm[2]), 'children' => []];
                if (strlen($mm[1]) === 2) {
                    $tree[] = $node;
                    $cur = count($tree) - 1;
                } elseif ($cur !== null) {
                    $tree[$cur]['children'][] = $node;
                } else {
                    $tree[] = $node;
                }
            }
        }

        return $tree;
    }

    private function sectionText(string $raw, string $name): string
    {
        $pattern = '/^##\s+'.preg_quote($name, '/').'\s*\n(.*?)(?=^##\s|\z)/ums';

        return preg_match($pattern, $raw, $m) ? $m[1] : '';
    }

    /** @return string[] */
    private function boldTerms(string $text, int $limit = 6): array
    {
        $out = [];
        if (preg_match_all('/\*\*([^*]+)\*\*/u', $text, $m)) {
            foreach ($m[1] as $t) {
                $t = trim($t, " .:\t");
                $lower = array_map('mb_strtolower', $out);
                if ($t !== '' && mb_strlen($t) <= 40 && count(explode(' ', $t)) <= 5
                    && ! in_array(mb_strtolower($t), $lower, true)) {
                    $out[] = $t;
                }
                if (count($out) >= $limit) {
                    break;
                }
            }
        }

        return $out;
    }

    private function glyphFor(string $text, int $i = 0): string
    {
        $low = mb_strtolower($text);
        foreach (self::GLYPHS as $key => $emoji) {
            if (str_contains($low, $key)) {
                return $emoji;
            }
        }

        return self::DEFAULT_GLYPHS[$i % count(self::DEFAULT_GLYPHS)];
    }
}
