<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Gym;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The Algorithm Pattern Gym — first native gym, ported from
 * gyms/algorithm-pattern-gym.html in the wiki. A recognition drill: read a
 * LeetCode-style prompt, name the pattern family within 8 seconds. Linked to
 * the DSA course (`/courses/dsa`) as its Practice.
 *
 *   ./run php artisan db:seed --class=GymSeeder
 *
 * Idempotent and non-destructive: the gym row is upserted and items are upserted
 * by `sort` (IDs preserved, so existing sessions/attempts stay valid); only tail
 * items are removed if the curriculum shrinks.
 *
 * Each item is tagged with the DSA course module it exercises, so telemetry can
 * be scoped per module (the seed of evidence-based module coverage). Tags are
 * resolved by module *title* at seed time; course seeders upsert modules in
 * place (IDs stable), so tags survive course re-seeds — re-run this seeder
 * only after renaming modules or changing the item→module mapping.
 */
class GymSeeder extends Seeder
{
    private const SLUG = 'algorithm-pattern-gym';

    public function run(): void
    {
        $course = Course::where('slug', 'dsa')->first();
        $modules = $course ? $course->modules()->pluck('id', 'title') : collect();

        DB::transaction(function () use ($course, $modules) {
            $gym = Gym::updateOrCreate(['slug' => self::SLUG], [
                'title' => 'Algorithm Pattern Gym',
                'subtitle' => 'Read a problem, name the pattern in 8 seconds.',
                'mode' => 'recognition',
                'target_reflex' => 'Read a LeetCode-style problem and classify it into the right algorithm '
                    .'pattern family (Hashmap, Two Pointers, Sliding Window, BFS, DP, …) in under 8 seconds.',
                'description' => "A distributional-recognition drill. The gym doesn't teach the pattern — it "
                    ."measures the recognition *reflex* under time pressure and points you at the near-miss you "
                    ."keep confusing. Pairs with the Data Structures & Algorithms course.",
                'course_id' => $course?->id,
                'domain_id' => $course?->domain_id,
                'timer_seconds' => 8,
                'round_count' => 20,
                'latency_target_ms' => 6000,
                'pass_accuracy' => 0.80,
                'promote_accuracy' => 0.85,
                // The blind-spot floor: 12 pattern families averaged into one
                // accuracy will read "reflexive" while a family sits at 0/2.
                // Zero any family and the rung is capped, however high the mean.
                'blind_spot_floor' => true,
                // Levels come from the Red Queen Knowledge Ladder (0–9), computed
                // by App\Support\KnowledgeLadder from pass/promote/latency above —
                // no per-gym stage ladder to maintain. The old ad-hoc `stages`
                // JSON (S1–S5) is retired; null it so re-seeding clears stale data.
                'stages' => null,
                'status' => Gym::STATUS_PUBLISHED,
                'source' => 'gyms/algorithm-pattern-gym.html (Neural-OS-Research wiki)',
            ]);

            // Upsert items by their stable position (sort) rather than delete-all,
            // so item IDs survive a re-seed and existing attempts keep referencing
            // the right prompt. `sort` is the natural key within a gym.
            $items = $this->items();
            foreach ($items as $i => $item) {
                $gym->items()->updateOrCreate(
                    ['sort' => $i],
                    [
                        'prompt' => $item[0],
                        'correct' => $item[1],
                        'choices' => $item[2],
                        'explanation' => $item[3],
                        'detail' => $item[4],
                        'module_id' => $modules->get($item[5]),
                    ],
                );
            }
            // Drop only the tail if the curriculum shrank (positions no longer present).
            $gym->items()->where('sort', '>=', count($items))->delete();

            $tagged = $gym->items()->whereNotNull('module_id')->count();
            $this->command?->info('Seeded gym "'.self::SLUG.'": '.$gym->items()->count()
                ." items, {$tagged} tagged to modules"
                .($course ? " (linked to course '{$course->slug}')" : ' (no DSA course found to link)'));
        });
    }

    /** @return list<array{0:string,1:string,2:array<string>,3:string,4:string,5:string}> prompt, correct, choices, explanation, near-miss, DSA module title */
    private function items(): array
    {
        return [
            ['Given an UNSORTED array, return the indices of two numbers that add to a target.',
                'Hashmap', ['Hashmap', 'Two Pointers', 'Binary Search', 'Sliding Window'],
                'Store value→index in a hashmap. For each x, look up target − x. O(n) time, O(n) space.',
                'Two Pointers needs sorted input; sorting would lose the original indices the problem asks for.',
                'Hashing, Heaps & Caches'],
            ['Given a SORTED array, find a pair that sums to a target.',
                'Two Pointers', ['Two Pointers', 'Hashmap', 'Binary Search', 'Sliding Window'],
                'L and R converge from edges. If sum < target, advance L; if sum > target, retreat R.',
                'Hashmap works but uses O(n) space. Two Pointers is O(1) space — pick this when sorted.',
                'Coding Patterns'],
            ['Find the longest substring of a string that contains no repeated characters.',
                'Sliding Window', ['Sliding Window', 'Hashmap', 'Two Pointers', 'DP'],
                'Variable window. Expand R; if a duplicate enters, contract L past the previous occurrence. Track max length.',
                'Hashmap is the helper inside the window. The structural pattern is the contiguous expanding/contracting window.',
                'Coding Patterns'],
            ['Find a peak element in an array (greater than both neighbors) in O(log n) — array is NOT sorted.',
                'Binary Search', ['Binary Search', 'Two Pointers', 'Linear Scan', 'DFS'],
                'Compare nums[mid] to nums[mid+1]; the side with the larger neighbor must contain a peak. Halve each step.',
                "Linear scan is O(n). The O(log n) requirement forces binary search even though the array isn't fully sorted.",
                'Sorting & Searching'],
            ['Rotting oranges in a grid: every minute, fresh oranges adjacent to a rotten one become rotten. Return minutes until all rotten.',
                'BFS', ['BFS', 'DFS', 'DP', 'Greedy'],
                'Multi-source BFS — start with all rotten oranges in the queue at time 0, then expand layer-by-layer.',
                "DFS doesn't track minutes uniformly across sources. The synchronous layer expansion is what makes BFS correct.",
                'Graph Algorithms'],
            ['Given edges of an UNDIRECTED graph arriving one at a time, detect the FIRST edge that creates a cycle.',
                'Union-Find', ['Union-Find', 'BFS', 'DFS', 'Topological Sort'],
                'For each edge (u,v): if find(u) == find(v), this edge closes a cycle. Otherwise union(u,v).',
                'BFS/DFS detect cycles in a built graph. UF is the canonical pattern for streaming/incremental edges.',
                'Trees & Disjoint Sets'],
            ['Find the K most frequent elements in a large unsorted array of n integers (k ≪ n).',
                'Heap', ['Heap', 'Sorting', 'Hashmap', 'Quickselect'],
                'Count via hashmap, then maintain a min-heap of size k over (count, value) pairs. O(n log k).',
                "Full sort is O(n log n). Heap of size k beats it when k ≪ n. Quickselect is O(n) but doesn't generalize to streaming.",
                'Hashing, Heaps & Caches'],
            ['You have an isBad(v) check. Versions become bad at some point and stay bad. Find the FIRST bad version.',
                'Binary Search', ['Binary Search', 'Linear Scan', 'Two Pointers', 'DFS'],
                "Monotonic predicate — 'first true' template. lo<hi; if isBad(mid), hi=mid; else lo=mid+1.",
                'Linear scan is O(n). The monotonic property of the predicate is what unlocks O(log n) binary search.',
                'Sorting & Searching'],
            ["For each day's temperature, return how many days until a WARMER day (or 0 if none).",
                'Monotonic Stack', ['Monotonic Stack', 'Two Pointers', 'Brute Force', 'Sliding Window'],
                'Maintain a stack of indices with decreasing temperatures. When today > stack top, pop and resolve that index.',
                "Two Pointers has no L/R convergence here. Brute Force is O(n²). The 'next greater' shape is a textbook monotonic stack.",
                'Linear Data Structures'],
            ['Largest rectangle area in a histogram (each bar has width 1, given heights).',
                'Monotonic Stack', ['Monotonic Stack', 'DP', 'Two Pointers', 'Binary Search'],
                'Increasing monotonic stack of indices. When a smaller bar arrives, pop and compute the rectangle bounded by the popped bar.',
                'DP solutions exist but are O(n²) without the stack insight. Stack gives clean O(n).',
                'Linear Data Structures'],
            ['Coin change: minimum number of coins to make a target amount, given coin denominations [1, 3, 4].',
                'DP 1D', ['DP 1D', 'Greedy', 'BFS', 'Backtracking'],
                'dp[a] = 1 + min(dp[a − c] for c in coins, if a ≥ c). Build bottom-up from 0 to amount.',
                'Greedy fails on [1,3,4] for amount 6: greedy picks 4+1+1=3 coins; DP finds 3+3=2 coins.',
                'Design Paradigms'],
            ['Given two strings, find the minimum number of insert/delete/replace operations to convert one to the other.',
                'DP 2D', ['DP 2D', 'Recursion', 'Backtracking', 'BFS'],
                'dp[i][j] = edit distance between s[0..i] and t[0..j]. Three transitions: match/replace/insert/delete.',
                'Plain recursion is exponential due to overlapping subproblems. The 2D state grid is the cure.',
                'Design Paradigms'],
            ['Generate ALL subsets of a given set of distinct integers.',
                'Backtracking', ['Backtracking', 'DP', 'BFS', 'Recursion only'],
                "For each element, branch 'include' or 'skip'. Record the partial set at each step (or only at length n).",
                "DP can count subsets but doesn't enumerate them. Backtracking explicitly builds and records each.",
                'Design Paradigms'],
            ['Detect a cycle in a DIRECTED graph.',
                'DFS', ['DFS', 'BFS', 'Union-Find', 'Backtracking'],
                'DFS with white/gray/black coloring. A gray-revisit means a back edge → cycle.',
                "Union-Find handles undirected graphs only. BFS needs in-degree tracking (Kahn's) to do the same job.",
                'Graph Algorithms'],
            ['Find the MEDIAN of a stream of integers — supports addNum(x) and findMedian() with many calls.',
                'Heap', ['Heap', 'Sorted Insert', 'BST', 'Sliding Window'],
                'Two heaps — max-heap for the lower half, min-heap for the upper half. Median is top of one (or average of tops).',
                'Sorted insert is O(n) per addNum. BST works but two-heaps is the canonical interview answer.',
                'Hashing, Heaps & Caches'],
            ['Find the maximum sum of any contiguous subarray (numbers may be negative).',
                'DP 1D', ['DP 1D', 'Sliding Window', 'Two Pointers', 'Brute Force'],
                "Kadane's: dp[i] = max(nums[i], dp[i-1] + nums[i]). Track running max of dp.",
                "Sliding Window assumes positive numbers — with negatives the window can't slide cleanly. Kadane handles negatives.",
                'Design Paradigms'],
            ["Word ladder: transform 'hit' → 'cog' by changing one letter at a time. Each intermediate word must be in a dictionary. Find SHORTEST sequence length.",
                'BFS', ['BFS', 'DFS', 'Dijkstra', 'DP'],
                'Words are nodes; edges between words differing by one letter. BFS guarantees shortest in unweighted graphs.',
                "DFS goes deep but doesn't give shortest. Dijkstra is for weighted edges — overkill here.",
                'Graph Algorithms'],
            ['Given the head of a singly linked list, return true if it has a cycle. Use O(1) extra space.',
                'Two Pointers', ['Two Pointers', 'Hashmap', 'Stack', 'Recursion'],
                "Floyd's tortoise-and-hare. Slow advances 1, fast advances 2. They meet iff a cycle exists.",
                'Hashmap solves it in O(n) space. Two Pointers is O(1) space — the canonical answer when O(1) is required.',
                'Coding Patterns'],
            ['Count contiguous subarrays whose sum equals k (numbers may be negative).',
                'Hashmap', ['Hashmap', 'Sliding Window', 'Two Pointers', 'Brute Force'],
                'Prefix sum + hashmap of prefix-count. For each position p, look up how many earlier prefixes equal p − k.',
                "Sliding Window assumes positive numbers — with negatives, expanding the window doesn't strictly increase the sum.",
                'Coding Patterns'],
            ['Validate that a binary tree is a binary search tree (left subtree < node < right subtree, recursively).',
                'DFS', ['DFS', 'BFS', 'Hashmap', 'Two Pointers'],
                'Recurse with (min, max) bounds per subtree, OR do an in-order traversal and check that the sequence is strictly increasing.',
                "BFS visits level-by-level and doesn't naturally enforce the in-order/bounds invariant.",
                'Trees & Disjoint Sets'],
        ];
    }
}
