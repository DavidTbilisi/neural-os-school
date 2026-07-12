<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Page;
use Database\Seeders\Concerns\UpsertsCourseCurriculum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "Neural OS — My Learning Framework": a hand-curated, published guided path
 * through the wiki's learning-system pages. The spine of the personal framework
 * ordered for building fluency — why encode-first works, the memory-palace
 * substrate, the CAST encoding engine, the retention science that makes it
 * stick, drilling to automaticity, measuring with METER, then applying it.
 *
 * Same shape as DsaCourseSeeder. Lessons resolve to already-PUBLIC wiki pages
 * (Page::public()); a few core pages are still private in the wiki and are
 * therefore omitted — see the note below. Idempotent.
 *
 *   ./run php artisan db:seed --class=NeuralOsCourseSeeder
 *
 * Private pages intentionally left out (publish in the wiki to include them):
 *   neural-os-daily-loop, neural-os-30-day-rollout, neural-os-research-thesis,
 *   software-design-principles-for-neural-os, neural-os-authoring-dsl.
 */
class NeuralOsCourseSeeder extends Seeder
{
    use UpsertsCourseCurriculum;

    private const SLUG = 'neural-os';

    /** Anchor page for the course's source/domain link. */
    private const ANCHOR = 'cast-overview';

    /**
     * The curriculum. Each module is [title, summary, lessons(slugs)]; a module
     * flagged 'optional' contributes its lessons as non-required (excluded from
     * progress %).
     */
    private function curriculum(): array
    {
        return [
            ['Orientation — a learning operating system', 'Why encode-first beats rote, and the memory substrate the whole system runs on.', [
                'learning-sciences-validation', 'long-term-memory', 'memory-palace',
            ]],
            ['The Memory Palace', 'The spatial substrate everything else hangs on — architecture, a language for it, and a path in without a mind\'s eye.', [
                'memory-palace-architecture-for-neural-os', 'mpl-syntax', 'memory-palace-for-aphantasia',
            ]],
            ['CAST — the encoding engine', 'Turn anything into a fast, exact mental image: the core system plus its encoding variants.', [
                'cast-overview', 'archetype-encoding-in-cast', 'delay-encoding-in-cast',
                'encoding-quantities-in-cast', 'edge-sign', 'rapid-in-neural-os',
            ]],
            ['Retention science', 'Why encoded knowledge sticks — and the review mechanics that keep it.', [
                'spaced-repetition', 'encoded-spaced-repetition', 'interleaving', 'tip-of-the-tongue',
            ]],
            ['Drilling to automaticity', 'From deliberate encoding to reflex: the drill ladder, maturity levels, and the progression it climbs.', [
                'automaticity-and-reflex-training', 'cast-drill-ladder',
                'maturity-levels-overview', 'skill-progression-stages',
            ]],
            ['Measure it — METER', 'Instrument your learning instead of guessing: the measurement layer.', [
                'meter-overview',
            ]],
            ['Put it to work', 'The framework applied — systems thinking, a worked domain, and how it meets Anki.', [
                'systems-thinking-and-cast-integration', 'math-learning-with-neural-os', 'cast-anki-requirements',
            ], 'optional'],
        ];
    }

    public function run(): void
    {
        $anchor = Page::where('slug', self::ANCHOR)->first();

        DB::transaction(function () use ($anchor) {
            $result = $this->upsertCourse(self::SLUG, [
                'title' => 'Neural OS — My Learning Framework',
                'subtitle' => 'The personal learning operating system as a guided, drillable path — encode, retain, automate, measure.',
                'description' => 'A guided pass through the framework, ordered for building fluency: understand why '
                    .'encode-first learning works, install the memory-palace substrate, learn the CAST encoding engine, '
                    .'then the retention science that makes it stick, the drills that take it to automaticity, and the '
                    .'METER layer that measures it. Every lesson is a wiki page you can read in place.',
                'source_page_id' => $anchor?->id,
                'domain_id' => $anchor?->domain_id,
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
