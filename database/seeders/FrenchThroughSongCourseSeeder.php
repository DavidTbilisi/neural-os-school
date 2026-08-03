<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Gym;
use App\Models\Page;
use Database\Seeders\Concerns\UpsertsCourseCurriculum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "French through Song" — A1 → A2, one grammar pattern per chorus.
 *
 * Transcribed from tools/french-music-drill/COURSE-MANIFEST.md in the wiki repo
 * (the canonical curriculum map — when the two drift, the manifest wins). Lesson
 * pages are the song units, staged into content/wiki/french-song/ with a `fr-`
 * slug prefix by ./sync-content.sh. Seeds as DRAFT — review in Filament, publish
 * deliberately. Idempotent (UpsertsCourseCurriculum).
 *
 * Run order (the fr- pages must exist and be public before lessons can link):
 *   ./sync-content.sh
 *   ./run php artisan wiki:import
 *   ./run php artisan wiki:publish-starter      # french-song is in its SAFE_DIRS
 *   ./run php artisan db:seed --class=FrenchThroughSongCourseSeeder
 *
 * Known gap it will warn about: `french-b1-roadmap` (Module "Why it works this
 * way") sits at the wiki ROOT, which wiki:publish-starter never touches — publish
 * it by hand in the admin if that lesson should appear.
 */
class FrenchThroughSongCourseSeeder extends Seeder
{
    use UpsertsCourseCurriculum;

    private const SLUG = 'french-through-song';

    /**
     * The four practice gyms, per the manifest. They exist today as wiki pages +
     * static HTML only; once any is ported to a native Gym row (GymSeeder
     * pattern), re-running this seeder mounts it onto the course.
     */
    private const PRACTICE_GYMS = [
        'dictee-gym',            // whole-course Practice: dictation over the song lines
        'l2-phonology-gym',      // Module 0 (soft link — the gym itself is multi-language)
        'vowel-grid-gym',        // Module 0
        'intonation-fluency-gym', // Module 0
    ];

    /**
     * Each module: [title, summary, lesson-slugs, ...flags]. Order and membership
     * mirror CURRICULUM.md exactly — same dependency order, same spiral review.
     * `optional` is module-level (trait constraint), so the reference pages all
     * live in the final optional module rather than beside their topic.
     */
    private function curriculum(): array
    {
        return [
            ['Read it out loud', 'How to turn written French into sound — silent finals, nasals, digraphs, liaison. Prerequisite to every song that follows; the rhyme only works if the rules fire.', [
                'fr-unit00a-silent-letters', 'fr-unit00b-nasals', 'fr-unit00c-digraphs',
            ]],
            ['The four pillars', 'être, avoir, aller, faire — the four irregular engines that power every sentence, plus futur proche for free. Ce soir is the module review.', [
                'fr-unit01-etre', 'fr-unit02-avoir', 'fr-unit03-aller', 'fr-unit04-faire', 'fr-ce-soir',
            ]],
            ['Naming & describing the world', 'il y a, articles and gender, the possessive/demonstrative/place systems, -er verbs, agreement.', [
                'fr-maison', 'fr-unit06-articles', 'fr-unit06b-possessives', 'fr-unit06c-demonstratives',
                'fr-unit06d-places-prepositions', 'fr-unit07-er-verbs', 'fr-unit08-adjectives',
            ]],
            ['Interacting', 'Asking, refusing, counting, telling time — the transactional core.', [
                'fr-unit09-questions', 'fr-unit10-negation', 'fr-unit10b-partitive', 'fr-unit11-numbers-time',
            ]],
            ['Daily life & wanting', 'Routines, commands, and the modal triangle vouloir/pouvoir/devoir.', [
                'fr-unit12-reflexives', 'fr-unit12b-imperative', 'fr-unit13-modals', 'fr-unit13b-il-faut',
                'fr-unit14-ir-re-verbs',
            ]],
            ['Past & beyond (A2)', 'The two passé composés, imparfait, futur/conditionnel, comparison, object pronouns.', [
                'fr-unit15-passe-compose-avoir', 'fr-unit16-passe-compose-etre', 'fr-unit17-imparfait',
                'fr-unit18-futur-conditionnel', 'fr-unit19-comparatives', 'fr-unit20-pronouns',
            ]],
            // Rows 20d (il/elle/on), 20e (nous/vous), 20f (ils/elles) are not written
            // yet — no slugs to list. When a unit lands in the wiki repo, sync stages
            // it automatically; add its fr- slug here and re-seed.
            ['The pronoun chart, row by row', 'One person taken across the whole grid per song. Unit 20 drilled the columns; these close the rows.', [
                'fr-unit20b-moi', 'fr-unit20c-toi',
            ]],
            ['Why it works this way', 'The design layer, for the curious learner: the B1 roadmap this course serves, the architecture behind it, and the full pronunciation reference.', [
                'french-b1-roadmap', 'language-learning-architecture', 'fr-reading-rules', 'fr-onset-peg-alphabet',
            ], 'optional'],
        ];
    }

    public function run(): void
    {
        $roadmap = Page::where('slug', 'french-b1-roadmap')->first();

        DB::transaction(function () use ($roadmap) {
            $result = $this->upsertCourse(self::SLUG, [
                'title' => 'French through Song',
                'subtitle' => 'A1 → A2 in ~30 songs — one grammar pattern per chorus, dictation and phonology gyms as practice.',
                'description' => 'The dependency-ordered path through French A1 (audited against Alliance Française '
                    .'Normandie\'s A1 syllabus, near point-for-point) into early A2. Every lesson is one song: the '
                    .'chorus is the target pattern, verses recycle ~70% prior vocab. Module 0 teaches reading-to-sound '
                    .'first — everything after assumes it. Practice happens in the dictée, phonology, vowel-grid and '
                    .'intonation gyms.',
                'source_page_id' => $roadmap?->id,
                'domain_id' => $roadmap?->domain_id,
                'status' => Course::STATUS_DRAFT,
                'sort' => 3,
            ], $this->curriculum());

            $mounted = Gym::whereIn('slug', self::PRACTICE_GYMS)
                ->update(['course_id' => $result['course']->id]);

            $this->command?->info("Seeded draft course '".self::SLUG."': "
                .$result['course']->modules()->count()." modules, {$result['lessons']} lessons, {$mounted} gyms mounted.");
            if ($result['missing'] !== []) {
                $this->command?->warn('  Skipped '.count($result['missing']).' unpublished/missing pages: '.implode(', ', $result['missing']));
            }
        });
    }
}
