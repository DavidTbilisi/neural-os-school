<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Hand-curated, published starter course: "Graph Theory".
 *
 * The first course to carry the ≥4 canvas representations (concept graph /
 * structure flow / matrix / memory palace / frozen scene) on every lesson page.
 * Lessons point at the already-public graph-theory/ pages; the hero pages also
 * get a curated `scene_json` (the "frozen scene" that would otherwise be authored
 * in the Filament admin). Idempotent.
 *
 *   ./run php artisan db:seed --class=GraphTheoryCourseSeeder
 */
class GraphTheoryCourseSeeder extends Seeder
{
    private const SLUG = 'graph-theory';

    /** Each module: [title, summary, lesson-slugs]. */
    private function curriculum(): array
    {
        return [
            ['Foundations', 'What a graph is, and how to measure its resilience — connectivity, cuts, and Menger.', [
                'graph-theory-overview', 'graph-connectivity',
            ]],
            ['Coloring & Planarity', 'Laying graphs flat and coloring them: chromatic number, the four-color map, Kuratowski.', [
                'graph-colorings', 'planar-graphs',
            ]],
            ['Matching, Flows & Cycles', 'Pairings, capacity networks, and spanning traversals.', [
                'matching-theory', 'network-flows', 'hamiltonian-cycles',
            ]],
            ['Extremal & Random Graphs', 'How many edges force structure, and what a typical graph looks like.', [
                'extremal-graph-theory', 'ramsey-theory', 'random-graphs',
            ]],
            ['Source Text', 'The reference this cluster is drawn from.', [
                'axenovich-graph-theory',
            ], 'optional'],
        ];
    }

    /** Curated frozen scenes for the hero pages (slug => scene_json). */
    private function scenes(): array
    {
        return [
            'graph-connectivity' => [
                'title' => 'The fragile bridge',
                'caption' => 'Two dense clusters joined by a single bridge edge and hinged on one cut-vertex — pull either and the graph shatters. The chain κ ≤ λ ≤ δ ranks how many pulls it can survive.',
                'sequence' => false,
                'elements' => [
                    ['glyph' => '🔴', 'label' => 'cut vertex — the lone hinge whose removal splits the graph'],
                    ['glyph' => '🌉', 'label' => 'bridge — the single edge whose loss disconnects'],
                    ['glyph' => '🧱', 'label' => 'block — a cohesive 2-connected cluster'],
                    ['glyph' => '♻️', 'label' => 'κ independent paths — Menger’s redundancy'],
                    ['glyph' => '📐', 'label' => 'δ min-degree — the ceiling on both cuts'],
                ],
            ],
            'network-flows' => [
                'title' => 'The city’s water mains',
                'caption' => 'Water races from source to sink through pipes of fixed capacity; the most you can push equals the cheapest set of pipes to cut. Ford–Fulkerson keeps finding a slack route until none remains.',
                'sequence' => true,
                'elements' => [
                    ['glyph' => '🚰', 'label' => 'source — where flow originates'],
                    ['glyph' => '➰', 'label' => 'augmenting path — a route with slack left'],
                    ['glyph' => '🔧', 'label' => 'capacity — each pipe’s ceiling'],
                    ['glyph' => '✂️', 'label' => 'min-cut — cheapest pipes to sever'],
                    ['glyph' => '🏙️', 'label' => 'sink — where flow drains'],
                ],
            ],
            'planar-graphs' => [
                'title' => 'Drawing without crossings',
                'caption' => 'A graph is planar if you can lay it flat with no edges crossing; Kuratowski says the only true obstructions are a hidden K5 or K3,3. Euler’s V − E + F = 2 keeps the faces honest.',
                'sequence' => false,
                'elements' => [
                    ['glyph' => '🗺️', 'label' => 'flat drawing — no edge crossings'],
                    ['glyph' => '🔵', 'label' => 'K5 — the complete-5 obstruction'],
                    ['glyph' => '⬛', 'label' => 'K3,3 — the utility-graph obstruction'],
                    ['glyph' => '🧩', 'label' => 'faces — V − E + F = 2'],
                ],
            ],
            'graph-colorings' => [
                'title' => 'The map with no touching colors',
                'caption' => 'Color the vertices so no edge joins two of the same color; the chromatic number is the fewest colors that works — and four always suffice for a planar map.',
                'sequence' => false,
                'elements' => [
                    ['glyph' => '🎨', 'label' => 'chromatic number χ — fewest colors'],
                    ['glyph' => '🗺️', 'label' => 'four-color theorem — planar maps'],
                    ['glyph' => '🔗', 'label' => 'adjacent vertices ≠ same color'],
                    ['glyph' => '🌡️', 'label' => 'greedy bound — χ ≤ Δ + 1'],
                ],
            ],
        ];
    }

    public function run(): void
    {
        $roadmap = Page::where('slug', 'graph-theory-overview')->first();

        DB::transaction(function () use ($roadmap) {
            Course::where('slug', self::SLUG)->get()->each->delete();

            $course = Course::create([
                'slug' => self::SLUG,
                'title' => 'Graph Theory',
                'subtitle' => 'The connectivity-first path through the graph-theory atlas — every lesson shown four+ ways.',
                'description' => 'A guided pass through the graph-theory cluster, ordered for fluency: what a graph is '
                    .'and how fragile it is, then coloring and planarity, then matchings, flows and cycles, and finally '
                    .'the extremal and random regimes. Every lesson page carries the same idea as a concept graph, a '
                    .'structure flow, a matrix, a memory palace, and a frozen scene.',
                'source_page_id' => $roadmap?->id,
                'domain_id' => $roadmap?->domain_id,
                'status' => Course::STATUS_PUBLISHED,
                'sort' => 1,
            ]);

            $pages = Page::public()->pluck('id', 'slug');
            $missing = [];
            $lessonCount = 0;

            foreach ($this->curriculum() as $mi => $row) {
                [$title, $summary, $slugs] = $row;
                $optional = ($row[3] ?? null) === 'optional';

                $module = $course->modules()->create(['title' => $title, 'summary' => $summary, 'sort' => $mi]);

                foreach ($slugs as $li => $slug) {
                    $pageId = $pages->get($slug);
                    if (! $pageId) {
                        $missing[] = $slug;

                        continue;
                    }
                    Lesson::create([
                        'module_id' => $module->id,
                        'page_id' => $pageId,
                        'title' => Page::whereKey($pageId)->value('title'),
                        'optional' => $optional,
                        'sort' => $li,
                    ]);
                    $lessonCount++;
                }
            }

            // Curated frozen scenes for the hero pages.
            $scened = 0;
            foreach ($this->scenes() as $slug => $scene) {
                $scened += Page::where('slug', $slug)->update(['scene_json' => $scene]);
            }

            $this->command?->info("Seeded published course '".self::SLUG."': "
                .$course->modules()->count()." modules, {$lessonCount} lessons, {$scened} curated scenes.");
            if ($missing !== []) {
                $this->command?->warn('  Skipped '.count($missing).' unpublished/missing pages: '.implode(', ', $missing));
            }
        });
    }
}
