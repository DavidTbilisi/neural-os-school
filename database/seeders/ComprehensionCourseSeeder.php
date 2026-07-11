<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Hand-curated, published course: "Comprehension".
 *
 * The comprehension layer of Neural OS as a guided path — why the gate exists
 * (fluency illusion, residue-of-thought, novice/expert), the human-owned
 * validation protocols (5 Gates, self-explanation, the Burger habits), the
 * builders (chunking, elaboration, mental models, BRIDGE LOAD), reading for
 * structure, and comprehension at scale (compression, ORIENT). Publishes the
 * two gate pages that were still private, since the course is built on them.
 * Idempotent.
 *
 *   ./run php artisan db:seed --class=ComprehensionCourseSeeder
 */
class ComprehensionCourseSeeder extends Seeder
{
    private const SLUG = 'comprehension';

    /** Pages this course depends on that must be published for lessons to attach. */
    private const PUBLISH = ['5-gates-of-comprehension', 'compression-for-comprehension-framework'];

    /** Each module: [title, summary, lesson-slugs]. */
    private function curriculum(): array
    {
        return [
            ['Why Comprehension First', 'The failure modes that make a gate necessary — recognition posing as understanding, unattended material, and what expertise actually changes.', [
                'pipeline-overview', 'fluency-illusion', 'memory-is-residue-of-thought', 'novice-vs-expert-cognition',
            ]],
            ['The Gates', 'The human-owned validation protocols: 5 Gates, self-explanation, and the Earth/Air habits they grew from.', [
                '5-gates-of-comprehension', 'self-explanation', 'understand-deeply-habit', 'socratic-question-generation',
            ]],
            ['Builders', 'The moves that construct understanding rather than test it — chunking, elaboration, mental models, and load-tested analogies.', [
                'chunking', 'elaboration', 'mental-models-for-learning', 'bridge-load',
            ]],
            ['Reading for Structure', 'Turning reading from colored highlighting into structural extraction, then drilling it to reflex.', [
                'semantic-reading-system', 'semantic-reading-drill-ladder', 'semantic-reading-recognition-gym',
            ]],
            ['Comprehension at Scale', 'Understanding a batch of materials as one generator plus deltas, and comprehending live environments you cannot re-read.', [
                'compression-for-comprehension-framework', 'orient-method',
            ]],
            ['Drill Ladder', 'Optional reps: the BRIDGE LOAD template library, drills, and spaced-repetition deck.', [
                'bridge-load-templates', 'bridge-load-drills', 'bridge-load-sr',
            ], 'optional'],
        ];
    }

    /** Curated frozen scenes for the hero pages (slug => scene_json). */
    private function scenes(): array
    {
        return [
            '5-gates-of-comprehension' => [
                'title' => 'The five locked doors',
                'caption' => 'Material queues at five doors before it may enter memory — place it on the map, show it three ways, shrink it to its smallest working example, try to break it, then rebuild it from a blank page. One stuck door means you don’t understand yet.',
                'sequence' => true,
                'elements' => [
                    ['glyph' => '🗺️', 'label' => 'LOCATE — situate it among neighbors and prerequisites'],
                    ['glyph' => '🎭', 'label' => 'REPRESENT — three distinct forms, smoothly switchable'],
                    ['glyph' => '🔬', 'label' => 'MINIMIZE — the smallest example that still works'],
                    ['glyph' => '🔨', 'label' => 'FALSIFY — hunt the edge case that breaks it'],
                    ['glyph' => '🔁', 'label' => 'REGENERATE — rebuild it from nothing, unaided'],
                ],
            ],
            'fluency-illusion' => [
                'title' => 'The mirage of the smooth page',
                'caption' => 'The re-read page glides by, and the glide feels like knowing — until the blank sheet asks for it back and nothing comes. Ease of recognition is a mirage; only retrieval is water.',
                'sequence' => false,
                'elements' => [
                    ['glyph' => '📖', 'label' => 're-reading — smooth, warm, and empty'],
                    ['glyph' => '🏜️', 'label' => 'the mirage — familiarity posing as competence'],
                    ['glyph' => '📝', 'label' => 'blank-page test — the only honest thermometer'],
                    ['glyph' => '💧', 'label' => 'retrieval — the real water'],
                ],
            ],
            'bridge-load' => [
                'title' => 'The load-tested bridge',
                'caption' => 'An analogy is a bridge from the known shore to the new one — built to carry real structure, posted with a weight limit where it breaks, and load-tested before anything is allowed to cross into memory.',
                'sequence' => false,
                'elements' => [
                    ['glyph' => '🪨', 'label' => 'known shore — the familiar source domain'],
                    ['glyph' => '🌉', 'label' => 'bridge — a mapping that preserves real structure'],
                    ['glyph' => '⚠️', 'label' => 'weight limit — where the analogy breaks, marked'],
                    ['glyph' => '🚚', 'label' => 'LOAD test — drive real weight across before trusting it'],
                ],
            ],
            'compression-for-comprehension-framework' => [
                'title' => 'The zip that proves you know',
                'caption' => 'A shelf of similar books collapses into one short generator plus a delta card per book. If you can unzip the shelf back from the generator, you understand it; if you can only recognize the spines, you don’t.',
                'sequence' => false,
                'elements' => [
                    ['glyph' => '📚', 'label' => 'the batch — many similar sources'],
                    ['glyph' => '🧬', 'label' => 'generator — the shared structure, stored once'],
                    ['glyph' => '🃏', 'label' => 'delta — the little each item alone adds'],
                    ['glyph' => '📦', 'label' => 'unzip test — regenerate the shelf from the generator'],
                ],
            ],
        ];
    }

    public function run(): void
    {
        DB::transaction(function () {
            // The course is built on the gate pages; publish them first so the
            // lesson pass below (which only sees public pages) can attach them.
            $published = Page::whereIn('slug', self::PUBLISH)
                ->where('visibility', '!=', Page::VISIBILITY_PUBLIC)
                ->update(['visibility' => Page::VISIBILITY_PUBLIC]);

            $roadmap = Page::where('slug', '5-gates-of-comprehension')->first();

            Course::where('slug', self::SLUG)->get()->each->delete();

            $course = Course::create([
                'slug' => self::SLUG,
                'title' => 'Comprehension',
                'subtitle' => 'The human-owned first gate of Neural OS — how to actually understand a thing before you encode it.',
                'description' => 'A guided pass through the comprehension layer, ordered for fluency: first why the '
                    .'gate exists at all — the fluency illusion, memory as residue of thought, and what separates expert '
                    .'from novice understanding — then the validation protocols themselves (5 Gates, self-explanation, '
                    .'the Burger habits), the moves that build understanding (chunking, elaboration, mental models, '
                    .'BRIDGE LOAD), reading for structure, and finally comprehension at scale: compressing a batch of '
                    .'materials into one generator, and ORIENTing in live environments you cannot re-read.',
                'source_page_id' => $roadmap?->id,
                'domain_id' => $roadmap?->domain_id,
                'status' => Course::STATUS_PUBLISHED,
                'sort' => 2,
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
                .$course->modules()->count()." modules, {$lessonCount} lessons, {$scened} curated scenes"
                .($published ? ", {$published} pages published" : '').'.');
            if ($missing !== []) {
                $this->command?->warn('  Skipped '.count($missing).' unpublished/missing pages: '.implode(', ', $missing));
            }
        });
    }
}
