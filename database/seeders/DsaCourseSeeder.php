<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Page;
use Database\Seeders\Concerns\UpsertsCourseCurriculum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A hand-curated, published starter course: "Data Structures & Algorithms".
 *
 * This is what the `courses:scaffold` draft becomes after an editor prunes it —
 * captured as a reproducible seeder (the courses layer's answer to
 * `wiki:publish-starter`). Lessons point at the already-published `programming/`
 * DSA atlas pages. Idempotent AND enrollment-safe: the curriculum is upserted
 * in place (course by slug, modules by title, lessons by page), so re-running
 * never touches enrollments, lesson completions, or gym-item module tags.
 *
 *   ./run php artisan db:seed --class=DsaCourseSeeder
 */
class DsaCourseSeeder extends Seeder
{
    use UpsertsCourseCurriculum;

    private const SLUG = 'dsa';

    /**
     * The curriculum. Each module is [title, summary, lessons(slugs)]; trailing
     * elements may be the string 'optional' (lessons contribute as non-required,
     * excluded from progress %) and/or an int target rung on the Knowledge
     * Ladder (defaults to 4 Classifiable — see KnowledgeLadder::DEFAULT_TARGET).
     */
    private function curriculum(): array
    {
        return [
            ['Foundations & Complexity', 'How to reason about algorithms before writing them: correctness, growth rates, and recurrences.', [
                'dsa-atlas', 'introduction-to-algorithms', 'asymptotic-notation', 'recursion',
                'master-theorem', 'amortized-analysis', 'loop-invariants',
            ]],
            ['Linear Data Structures', 'The sequential containers everything else is built on.', [
                'array', 'linked-list', 'stack', 'queue', 'monotonic-stack',
            ]],
            ['Hashing, Heaps & Caches', 'Constant-time lookup, priority ordering, and probabilistic structures.', [
                'hash-table', 'heap', 'lru-cache', 'bloom-filter', 'skip-list',
            ]],
            ['Trees & Disjoint Sets', 'Hierarchical and range structures — from BSTs to segment trees.', [
                'binary-tree', 'binary-search-tree', 'avl-tree', 'b-tree', 'trie',
                'segment-tree', 'fenwick-tree', 'union-find', 'suffix-array',
            ]],
            ['Sorting & Searching', 'The canonical divide-and-conquer and comparison workhorses.', [
                'linear-search', 'binary-search', 'merge-sort', 'quicksort',
            ]],
            ['Design Paradigms', 'The three big strategies — greedy, dynamic programming, backtracking — with worked DP examples.', [
                'greedy-algorithms', 'dynamic-programming', 'knapsack',
                'longest-common-subsequence', 'backtracking', 'approximation-algorithms',
            ]],
            ['Graph Algorithms', 'Traversal, shortest paths, spanning trees, and connectivity.', [
                'bfs', 'dfs', 'topological-sort', 'dijkstra', 'bellman-ford',
                'floyd-warshall', 'a-star', 'kruskal-prim', 'tarjans-scc',
            ]],
            // Target rung 7 (Reflexive): this is the module the Algorithm Pattern
            // Gym exists for — naming the pattern fast IS the competency, so
            // covered means accurate under the gym's latency target, not just accurate.
            ['Coding Patterns', 'Reusable scan techniques that turn O(n²) brute force into O(n).', [
                'two-pointer', 'fast-slow-pointer', 'sliding-window', 'prefix-sum',
            ], 7],
            ['Strings, Math & Hardness', 'String matching, number-theory tools, and the limits of tractability.', [
                'kmp', 'sieve-of-eratosthenes', 'fast-exponentiation', 'fft', 'np-completeness',
            ]],
            ['Practice & Retention', 'Drill ladders and spaced-repetition decks — train the atlas to automaticity.', [
                'algorithm-foundations-drill-ladder', 'dsa-tier1-drill-ladder', 'algorithm-foundations-sr',
            ], 'optional'],
        ];
    }

    public function run(): void
    {
        $roadmap = Page::where('slug', 'dsa-roadmap')->first();

        DB::transaction(function () use ($roadmap) {
            // Drop the pre-courses-layer scaffold demo if it's still around —
            // a separate course, not the one being upserted.
            Course::where('slug', 'dsa-roadmap')->get()->each->delete();

            $result = $this->upsertCourse(self::SLUG, [
                'title' => 'Data Structures & Algorithms',
                'subtitle' => 'From Big-O to graph algorithms — the published DSA atlas as a guided, drillable path.',
                'description' => "A complete pass through the classic DSA curriculum, ordered for building fluency: "
                    ."reason about complexity first, then the structures, then the algorithm-design paradigms that "
                    ."reuse them. Every lesson is a wiki page you can read in place; the final module is drills and "
                    ."spaced-repetition decks to take the atlas to automaticity.",
                'source_page_id' => $roadmap?->id,
                'domain_id' => $roadmap?->domain_id,
                'status' => Course::STATUS_PUBLISHED,
                'sort' => 0,
            ], $this->curriculum());

            $this->command?->info("Seeded published course '".self::SLUG."': "
                .$result['course']->modules()->count()." modules, {$result['lessons']} lessons.");

            if ($result['missing'] !== []) {
                $this->command?->warn('  Skipped '.count($result['missing']).' unpublished/missing pages: '.implode(', ', $result['missing']));
            }
        });
    }
}
