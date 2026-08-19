<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Gym;
use App\Models\Page;
use Database\Seeders\Concerns\UpsertsCourseCurriculum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "Linux: Bash Scripting" — the shell as a language, dependency-ordered.
 *
 * Lessons point at the `linux/` wiki cluster rooted at `bash-atlas`, authored
 * for this course (see the 2026-08-19 entry in the wiki's log.md). Module order
 * mirrors the atlas's blocks exactly — when the two drift, the atlas wins,
 * because it is the page a reader lands on.
 *
 * The course has one spine: a shell line is not executed, it is *expanded* and
 * then executed. `expansion-order` is the hinge, which is why module 1 is short
 * and module 2 is not — everything downstream assumes the reader can predict
 * what a line expands to before it runs.
 *
 * Run order (the linux/ pages must exist and be public before lessons can link):
 *   ./sync-content.sh
 *   ./run php artisan wiki:import
 *   ./run php artisan wiki:publish-starter      # `linux` is in its SAFE_DIRS
 *   ./run php artisan db:seed --class=LinuxBashCourseSeeder
 *   ./run php artisan db:seed --class=BashPatternGymSeeder
 *
 * Idempotent and enrollment-safe (UpsertsCourseCurriculum).
 */
class LinuxBashCourseSeeder extends Seeder
{
    use UpsertsCourseCurriculum;

    private const SLUG = 'linux-bash';

    /** The gym mounted as this course's Practice, once BashPatternGymSeeder has run. */
    private const PRACTICE_GYMS = ['bash-pattern-gym'];

    /**
     * Each module is [title, summary, lesson-slugs, ...flags]; flags are
     * 'optional' (non-required, excluded from progress %) and/or an int target
     * rung on the Knowledge Ladder (default 4 Classifiable).
     */
    private function curriculum(): array
    {
        return [
            ['The shell as a language', 'What a shell is, how a typed line becomes an argument vector, and the ordered expansion pipeline every other module depends on.', [
                'bash-atlas', 'shell-and-command-anatomy', 'expansion-order',
            ]],
            // Target rung 7 (Reflexive): quoting is the module the gym exists
            // for. Spotting an unquoted expansion has to be a reflex at reading
            // speed — knowing the rule and still writing `rm $file` is the
            // failure this course is built to prevent, so covered here means
            // fast, not merely accurate.
            ['Quoting and expansion', 'Which expansions each quoting form switches off, what IFS actually splits, and the string tools that need no subprocess. The single biggest source of real shell bugs.', [
                'quoting-rules', 'word-splitting-and-ifs', 'parameter-expansion',
                'command-substitution-and-arithmetic',
            ], 7],
            ['Files, globs, redirection and pipes', 'Where the shell finally touches real files: pattern matching against the filesystem, the file-descriptor table, and the concurrency a pipe creates.', [
                'globbing', 'redirection', 'pipes-and-process-substitution', 'here-documents',
            ]],
            ['Control flow', 'Zero means true. Exit status, the three conditional constructs and when each is right, loops that survive filenames with spaces, and glob-pattern dispatch.', [
                'exit-status-and-control-flow', 'test-and-double-bracket', 'loops', 'case-statements',
            ]],
            ['Functions, arrays and parameters', 'Structure: a function is a named command, an array is the only thing that carries word boundaries, and "$@" is the only correct way to forward arguments.', [
                'functions-and-scope', 'arrays-and-associative-arrays',
                'positional-parameters-and-getopts', 'variables-and-the-environment',
            ]],
            ['Processes, signals and traps', 'What forks and what does not, why a value went missing across a fork, and how to guarantee cleanup with trap … EXIT.', [
                'processes-and-job-control', 'subshells-and-execution-context', 'signals-and-traps',
            ]],
            ['The text pipeline', 'The tools a shell exists to compose: grep, sed, awk, the coreutils filters, and find/xargs with the NUL rule that makes filename handling correct.', [
                'grep-and-regex', 'sed', 'awk', 'text-toolkit', 'find-and-xargs',
            ]],
            ['Writing scripts that do not break', 'Strict mode and the places it stands aside, the skeleton a script other people run needs, and the two tools that find the rest.', [
                'strict-mode', 'script-structure-and-cli-design', 'debugging-bash',
                'shellcheck-and-static-analysis',
            ]],
            ['Reference and practice', 'The failure-first index into the cluster, and the recognition ladder that turns the rules into reflexes.', [
                'bash-pitfalls-catalog', 'bash-drill-ladder',
            ], 'optional'],
        ];
    }

    public function run(): void
    {
        $atlas = Page::where('slug', 'bash-atlas')->first();

        DB::transaction(function () use ($atlas) {
            $result = $this->upsertCourse(self::SLUG, [
                'title' => 'Linux: Bash Scripting',
                'subtitle' => 'The shell as a language — expansion, quoting, control flow, the text pipeline, and scripts that survive review.',
                'description' => 'A dependency-ordered path through bash, built around one idea: a shell line is not '
                    .'executed, it is *expanded* and then executed. Module 1 establishes the expansion pipeline; '
                    .'everything after it is a rule about that transformation, so the modules are not '
                    .'interchangeable. Linux appears where the shell touches it — processes, signals, permissions, '
                    .'the environment a cron job actually gets. Every lesson carries a Distinguisher against the '
                    .'near-miss you keep confusing it with, and a Failure mode drawn from bugs that ship. '
                    .'Practice is the Bash Pattern Gym: read a line, call it safe or unsafe, before it runs.',
                'source_page_id' => $atlas?->id,
                'domain_id' => $atlas?->domain_id,
                'status' => Course::STATUS_PUBLISHED,
                'sort' => 4,
            ], $this->curriculum());

            $mounted = Gym::whereIn('slug', self::PRACTICE_GYMS)
                ->update(['course_id' => $result['course']->id]);

            $this->command?->info("Seeded published course '".self::SLUG."': "
                .$result['course']->modules()->count()." modules, {$result['lessons']} lessons, {$mounted} gyms mounted.");

            if ($result['missing'] !== []) {
                $this->command?->warn('  Skipped '.count($result['missing']).' unpublished/missing pages: '.implode(', ', $result['missing']));
            }
        });
    }
}
