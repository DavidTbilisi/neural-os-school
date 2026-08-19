<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Gym;
use App\Models\Lesson;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The Bash Pattern Gym — practice for the "Linux: Bash Scripting" course.
 *
 * A recognition drill on the one reflex the course exists to build: read a line
 * of shell and name the defect family before running it. The answer set is a
 * classification of *why* shell breaks, not a list of commands — which is what
 * makes the blind-spot floor meaningful here (`gym_items.correct` values are
 * category labels, so a learner who is 90% overall but 0-for-2 on "subshell
 * state loss" is capped rather than promoted).
 *
 * "Correct as written" is a deliberate category: without it the drill teaches
 * "always find a bug", which is its own failure mode in review.
 *
 *   ./run php artisan db:seed --class=BashPatternGymSeeder
 *
 * Idempotent and non-destructive, exactly like GymSeeder: the gym row is
 * upserted and items are upserted by `sort` (IDs preserved, so existing
 * sessions/attempts stay valid); only tail items are removed if the bank shrinks.
 *
 * Each item is tagged with the course module it exercises AND the lesson it
 * belongs to, so it both feeds `Report::moduleEvidence()` and renders as an
 * in-lesson check on that lesson's page. Tags resolve by module title / page
 * slug at seed time; course seeders upsert in place, so tags survive re-seeds.
 */
class BashPatternGymSeeder extends Seeder
{
    private const SLUG = 'bash-pattern-gym';

    private const COURSE = 'linux-bash';

    public function run(): void
    {
        $course = Course::where('slug', self::COURSE)->first();
        $modules = $course ? $course->modules()->pluck('id', 'title') : collect();

        // page slug => lesson id, for this course only.
        $lessons = $course
            ? Lesson::whereIn('module_id', $modules->values())
                ->join('pages', 'pages.id', '=', 'lessons.page_id')
                ->pluck('lessons.id', 'pages.slug')
            : collect();

        DB::transaction(function () use ($course, $modules, $lessons) {
            $gym = Gym::updateOrCreate(['slug' => self::SLUG], [
                'title' => 'Bash Pattern Gym',
                'subtitle' => 'Read a line of shell, name the defect in 10 seconds.',
                'mode' => 'recognition',
                'target_reflex' => 'Read one line of shell and classify it into the right defect family '
                    .'(unquoted expansion, subshell state loss, ignored exit status, redirection order, …) '
                    .'— or correctly call it clean — in under 10 seconds.',
                'description' => "A distributional-recognition drill for code review. The gym doesn't teach the "
                    ."rule — it measures whether the wrongness of `rm \$file` arrives *before* the reasoning does, "
                    ."the way a misspelling looks wrong before you can name the rule. Pairs with the Linux: Bash "
                    ."Scripting course. One answer in the set is \"correct as written\", so the drill measures "
                    .'judgement rather than rewarding suspicion.',
                'course_id' => $course?->id,
                'domain_id' => $course?->domain_id ?? Page::where('slug', 'bash-atlas')->value('domain_id'),
                'timer_seconds' => 10,
                'round_count' => 20,
                'latency_target_ms' => 8000,
                'pass_accuracy' => 0.80,
                'promote_accuracy' => 0.85,
                // Twelve defect families averaged into one accuracy will read
                // "reflexive" while "subshell state loss" sits at 0/2 — and that
                // family is exactly the one that costs a production incident.
                'blind_spot_floor' => true,
                'stages' => null,
                'status' => Gym::STATUS_PUBLISHED,
                'source' => 'wiki/linux/ (Neural-OS-Research wiki) — item bank drawn from the failure-mode sections',
            ]);

            $items = $this->items();
            foreach ($items as $i => $item) {
                [$prompt, $correct, $choices, $explanation, $detail, $moduleTitle, $lessonSlug] = $item;

                $gym->items()->updateOrCreate(
                    ['sort' => $i],
                    [
                        'prompt' => $prompt,
                        'correct' => $correct,
                        'choices' => $choices,
                        'explanation' => $explanation,
                        'detail' => $detail,
                        'module_id' => $modules->get($moduleTitle),
                        'lesson_id' => $lessons->get($lessonSlug),
                    ],
                );
            }
            $gym->items()->where('sort', '>=', count($items))->delete();

            $tagged = $gym->items()->whereNotNull('module_id')->count();
            $onLessons = $gym->items()->whereNotNull('lesson_id')->count();
            $this->command?->info('Seeded gym "'.self::SLUG.'": '.$gym->items()->count()
                ." items, {$tagged} tagged to modules, {$onLessons} mounted on lessons"
                .($course ? " (linked to course '{$course->slug}')" : ' (no linux-bash course found to link)'));
        });
    }

    /**
     * @return list<array{0:string,1:string,2:array<string>,3:string,4:string,5:string,6:string}>
     *         prompt, correct, choices, explanation, near-miss, module title, lesson page slug
     */
    private function items(): array
    {
        // The twelve defect families this gym classifies into — these strings are
        // the blind-spot categories, so every one of them carries >= 2 items.
        //   Unquoted expansion · Word splitting · Subshell state loss · Exit status
        //   ignored · Redirection order · Glob vs regex · String vs numeric test ·
        //   Array vs string · Filename safety · Portability · Expansion order ·
        //   Correct as written
        $pick = fn (string ...$c) => $c;

        return [
            // --- Expansion order -------------------------------------------------
            ['n=5; echo {1..$n}',
                'Expansion order', $pick('Expansion order', 'Unquoted expansion', 'String vs numeric test', 'Correct as written'),
                'Brace expansion is the FIRST expansion step, before parameter expansion — so $n has not been substituted yet and bash emits the literal {1..$n}.',
                'Not a quoting bug: quoting cannot help, because the problem is the order. A computed bound needs seq or a C-style for (( )).',
                'The shell as a language', 'expansion-order'],

            ['cmd=\'ls "my dir"\'; $cmd',
                'Expansion order', $pick('Expansion order', 'Array vs string', 'Unquoted expansion', 'Correct as written'),
                'Quote removal already ran for the literal line. By the time $cmd expands, its quotes are ordinary characters, so ls receives "my and dir" as two words.',
                'Quoting it as "$cmd" does not fix it either — that makes one word. The fix is an array: cmd=(ls "my dir"); "${cmd[@]}".',
                'The shell as a language', 'expansion-order'],

            // --- Unquoted expansion ----------------------------------------------
            ['rm -rf $tmpdir/*',
                'Unquoted expansion', $pick('Unquoted expansion', 'Filename safety', 'Glob vs regex', 'Correct as written'),
                'An unquoted $tmpdir splits on spaces; worse, if it is empty the line becomes rm -rf /*. Quote it and guard: [[ -n ${tmpdir:-} ]] && rm -rf -- "$tmpdir"/*',
                'The glob is not the defect — * is intended here. The variable in front of it is.',
                'Quoting and expansion', 'quoting-rules'],

            ['cp $src "$dst"',
                'Unquoted expansion', $pick('Unquoted expansion', 'Word splitting', 'Filename safety', 'Correct as written'),
                '$src is unquoted, so a path containing a space becomes two arguments and cp reports "cannot stat". $dst is already correct — the asymmetry is the tell.',
                'Not word splitting as a category question: the fix here is simply the missing quotes, not an IFS change.',
                'Quoting and expansion', 'quoting-rules'],

            ['if [ -f $file ]; then process "$file"; fi',
                'Unquoted expansion', $pick('Unquoted expansion', 'String vs numeric test', 'Portability', 'Correct as written'),
                '[ is a command, so its operands are word-split. An empty $file makes it [ -f ] (wrong result); a $file with a space makes it "too many arguments".',
                'Inside [[ ]] the same line would be safe, because [[ is a keyword and suppresses splitting — but the line as written uses [.',
                'Control flow', 'test-and-double-bracket'],

            // --- Word splitting ---------------------------------------------------
            ['while read line; do printf "%s\\n" "$line"; done < input.txt',
                'Word splitting', $pick('Word splitting', 'Subshell state loss', 'Unquoted expansion', 'Correct as written'),
                'Missing IFS= and -r: leading/trailing whitespace is stripped by IFS trimming, and backslashes are eaten. The correct form is: while IFS= read -r line',
                'The redirection is right — this loop is NOT in a subshell, so no state is lost. The defect is entirely in how read is called.',
                'Quoting and expansion', 'word-splitting-and-ifs'],

            ['for f in $(ls *.log); do gzip "$f"; done',
                'Word splitting', $pick('Word splitting', 'Filename safety', 'Exit status ignored', 'Correct as written'),
                "ls's output is word-split on whitespace and then globbed, so a file named \"app error.log\" becomes two nonexistent arguments.",
                'Quoting "$(ls *.log)" does not fix it — that yields one word. Iterate the glob directly: for f in *.log (with shopt -s nullglob).',
                'Control flow', 'loops'],

            // --- Subshell state loss ---------------------------------------------
            ['n=0; grep -c . file | while read -r c; do (( n += c )); done; echo "$n"',
                'Subshell state loss', $pick('Subshell state loss', 'Exit status ignored', 'Word splitting', 'Correct as written'),
                'Every stage of a pipeline runs in its own subshell, so n is incremented in a forked copy and echo prints 0. Use: done < <(grep -c . file)',
                'Not an arithmetic bug — (( n += c )) is correct. The value is computed and then discarded with the subshell.',
                'Processes, signals and traps', 'subshells-and-execution-context'],

            ['find . -name "*.tmp" | xargs rm; cd /var/log',
                'Filename safety', $pick('Filename safety', 'Subshell state loss', 'Unquoted expansion', 'Correct as written'),
                'Bare find | xargs splits on whitespace and treats quotes and backslashes specially, so ordinary filenames with spaces are mangled. Use -print0 with xargs -0.',
                'The cd is fine — it is not inside a pipeline, so it does affect the shell.',
                'The text pipeline', 'find-and-xargs'],

            ['out=$(cd "$dir" && pwd); cd "$dir" || exit',
                'Correct as written', $pick('Correct as written', 'Subshell state loss', 'Exit status ignored', 'Unquoted expansion'),
                'The $( ) subshell is used exactly as intended — for its output, not its side effects — and the real cd is guarded with || exit. Nothing here is wrong.',
                "The tempting wrong answer is \"subshell state loss\": the cd inside \$( ) really is local to the subshell, but the line does not depend on it persisting.",
                'Processes, signals and traps', 'subshells-and-execution-context'],

            ['(cd build && make) && echo done',
                'Correct as written', $pick('Correct as written', 'Subshell state loss', 'Exit status ignored', 'Redirection order'),
                'The parentheses are deliberate isolation: the directory change is confined to the subshell, and && propagates failure correctly.',
                'This is the good use of the construct that item "n=0; … | while read" gets wrong — the difference is whether you needed state to survive.',
                'Processes, signals and traps', 'subshells-and-execution-context'],

            // --- Exit status ignored ---------------------------------------------
            ['set -euo pipefail; curl -s "$url" | tar xzf -',
                'Exit status ignored', $pick('Exit status ignored', 'Redirection order', 'Correct as written', 'Portability'),
                'pipefail IS set here, so this one is close — but curl -s without -f exits 0 on an HTTP 404 and pipes the error page into tar. The status you are checking is the wrong one.',
                'Adding pipefail is not enough when the upstream tool reports failure in the payload rather than the status. curl needs --fail.',
                'Writing scripts that do not break', 'strict-mode'],

            ['local out=$(some_command); if (( $? != 0 )); then die; fi',
                'Exit status ignored', $pick('Exit status ignored', 'Subshell state loss', 'String vs numeric test', 'Correct as written'),
                "local is itself a command, so \$? is local's status — always 0. some_command's failure is invisible. Declare first: local out; out=\$(some_command) || die",
                'Not a subshell problem: the output is captured correctly. Only the status is lost.',
                'Functions, arrays and parameters', 'functions-and-scope'],

            ['set -e; count=0; (( count++ )); echo "still running"',
                'Exit status ignored', $pick('Exit status ignored', 'String vs numeric test', 'Expansion order', 'Correct as written'),
                '(( count++ )) evaluates to 0 (the pre-increment value), and an arithmetic command whose result is 0 returns status 1 — so set -e aborts before the echo.',
                'Use (( ++count )), or append || true. The variable is incremented either way; it is the exit status that kills the script.',
                'Writing scripts that do not break', 'strict-mode'],

            // --- Redirection order -------------------------------------------------
            ['make 2>&1 > build.log',
                'Redirection order', $pick('Redirection order', 'Exit status ignored', 'Portability', 'Correct as written'),
                'Redirections apply left to right. 2>&1 copies where fd 1 points AT THAT MOMENT (the terminal), then > moves fd 1 to the file. Errors go to the terminal, output to the file.',
                'The intended form is make > build.log 2>&1. Both spellings are valid shell — only one does what was meant.',
                'Files, globs, redirection and pipes', 'redirection'],

            ['sudo tee -a /etc/hosts <<< "127.0.0.1 dev" > /dev/null',
                'Correct as written', $pick('Correct as written', 'Redirection order', 'Unquoted expansion', 'Portability'),
                'This is the correct pattern for writing to a root-owned file: the privileged write is done by tee, not by the shell, and > /dev/null just silences the echo-back.',
                'The wrong version is sudo echo … > /etc/hosts, where YOUR unprivileged shell performs the redirection and fails.',
                'Files, globs, redirection and pipes', 'redirection'],

            // --- Glob vs regex -----------------------------------------------------
            ['grep "*.log" access.log',
                'Glob vs regex', $pick('Glob vs regex', 'Unquoted expansion', 'Filename safety', 'Correct as written'),
                'grep takes a REGEX, where * means "zero or more of the preceding atom" — so *.log is an invalid-or-degenerate pattern, not "files ending in .log". The glob meaning does not apply.',
                'The quoting is right (it stops the shell expanding it). The pattern language is what is wrong.',
                'The text pipeline', 'grep-and-regex'],

            ['case $f in +([0-9]).txt) echo numeric ;; esac',
                'Glob vs regex', $pick('Glob vs regex', 'Portability', 'Expansion order', 'Correct as written'),
                'case matches GLOBS. +(…) is an extglob operator that only exists after shopt -s extglob — without it, + is a literal character and the branch never fires.',
                'A regex reader expects + to mean "one or more" unconditionally. In case patterns it does not, and there is no error to tell you.',
                'Control flow', 'case-statements'],

            ['pat="*.txt"; if [[ $f == "$pat" ]]; then echo match; fi',
                'Glob vs regex', $pick('Glob vs regex', 'Unquoted expansion', 'String vs numeric test', 'Correct as written'),
                'Quoting the RIGHT side of == makes it a literal string comparison, so this matches only a file actually named *.txt. Unquoted, $pat is matched as a glob pattern.',
                'This is the one place in bash where removing quotes is the fix. For a real regex you would need =~ with the pattern in an unquoted variable.',
                'Files, globs, redirection and pipes', 'globbing'],

            // --- String vs numeric test -------------------------------------------
            ['if [[ $count > 9 ]]; then echo many; fi',
                'String vs numeric test', $pick('String vs numeric test', 'Unquoted expansion', 'Exit status ignored', 'Correct as written'),
                'Inside [[ ]], > is a LEXICAL string comparison. With count=10 this is false, because the string "10" sorts before "9". Use (( count > 9 )) or [[ $count -gt 9 ]].',
                'Inside single brackets it is worse: [ $count > 9 ] is a redirection that creates a file named 9 and succeeds.',
                'Control flow', 'test-and-double-bracket'],

            ['month=$(date +%m); if (( month == 8 )); then echo august; fi',
                'String vs numeric test', $pick('String vs numeric test', 'Expansion order', 'Portability', 'Correct as written'),
                'date +%m returns a zero-padded 08, and a leading zero means OCTAL inside (( )) — 08 is not a valid octal literal, so this is a fatal arithmetic error. Force base 10: $(( 10#$month )).',
                'It is not a quoting problem, and it works fine for months 10-12 — which is why it ships in January and fails in August.',
                'Control flow', 'exit-status-and-control-flow'],

            // --- Array vs string ---------------------------------------------------
            ['files=(a.txt "b c.txt"); cp ${files[*]} /backup/',
                'Array vs string', $pick('Array vs string', 'Unquoted expansion', 'Word splitting', 'Correct as written'),
                '[*] joins every element into ONE word using the first character of IFS, and unquoted it is then re-split — so the boundaries the array existed to preserve are destroyed. Use "${files[@]}".',
                'Quoting it as "${files[*]}" is also wrong: that passes a single argument "a.txt b c.txt". Only "${files[@]}" is right.',
                'Functions, arrays and parameters', 'arrays-and-associative-arrays'],

            ['opts="--exclude=\'my dir\' -a"; rsync $opts src/ dst/',
                'Array vs string', $pick('Array vs string', 'Expansion order', 'Unquoted expansion', 'Correct as written'),
                'A string cannot carry word boundaries through expansion — the quotes inside $opts are literal characters by then. Build the flags as an array: opts=(-a --exclude="my dir"); rsync "${opts[@]}" …',
                'This is the same root cause as $cmd above, met in its most common disguise: "I want to add a flag conditionally".',
                'Functions, arrays and parameters', 'arrays-and-associative-arrays'],

            // --- Filename safety ---------------------------------------------------
            ['cat urls.txt | while read -r u; do fetch "$u" || failed=1; done; [[ $failed ]] && exit 1',
                'Subshell state loss', $pick('Subshell state loss', 'Exit status ignored', 'Word splitting', 'Correct as written'),
                'The while loop is a pipeline stage and therefore a subshell, so failed never reaches the test — the script exits 0 however many fetches failed. Use: done < urls.txt',
                'There is also a useless cat, but that costs a fork, not correctness. Redirecting from the file fixes both at once.',
                'Files, globs, redirection and pipes', 'pipes-and-process-substitution'],

            ['sort -u data.txt > data.txt',
                'Redirection order', $pick('Redirection order', 'Exit status ignored', 'Filename safety', 'Correct as written'),
                'The shell opens and TRUNCATES data.txt before sort ever runs, so sort reads an empty file and the data is gone. Redirection setup always precedes execution.',
                'Not a sort bug — any command with the same file on both sides does this. Write to a temp file and mv, or use sponge.',
                'Files, globs, redirection and pipes', 'redirection'],

            ['find . -type f -print0 | xargs -0 -r sha256sum',
                'Correct as written', $pick('Correct as written', 'Filename safety', 'Exit status ignored', 'Word splitting'),
                'NUL-separated on both sides, and -r stops xargs running at all on empty input. This is the reference-correct shape.',
                'The near-miss is the same line without -print0/-0, which is the single most common real-world filename bug.',
                'The text pipeline', 'find-and-xargs'],

            ['find /tmp -delete -name "*.sock"',
                'Filename safety', $pick('Filename safety', 'Glob vs regex', 'Redirection order', 'Correct as written'),
                "find's expression is evaluated left to right: -delete comes first and matches everything, so the whole tree goes before -name is ever consulted.",
                'The pattern is correctly quoted and the syntax is legal — which is exactly why this one is dangerous. Actions must follow their filters.',
                'The text pipeline', 'find-and-xargs'],

            // --- Portability --------------------------------------------------------
            ['#!/bin/sh' . "\n" . 'declare -A seen; seen[a]=1',
                'Portability', $pick('Portability', 'Array vs string', 'Expansion order', 'Correct as written'),
                'A #!/bin/sh shebang is a promise of no bashisms. Associative arrays are bash 4+ only, and on Debian/Ubuntu /bin/sh is dash, where declare does not exist.',
                'It runs fine on distributions where /bin/sh is a symlink to bash — which is how this survives testing and fails in CI.',
                'Writing scripts that do not break', 'script-structure-and-cli-design'],

            ['sed -i "s/foo/bar/" config.txt',
                'Portability', $pick('Portability', 'Glob vs regex', 'Unquoted expansion', 'Correct as written'),
                'GNU sed takes -i bare; BSD/macOS sed requires an argument, so on macOS this consumes the script as the backup suffix and creates a file named s/foo/bar/.',
                'The substitution itself is correct. Only the in-place flag differs between the two implementations.',
                'The text pipeline', 'sed'],

            ['tmpdir=$(mktemp -d); trap "rm -rf $tmpdir" EXIT',
                'Unquoted expansion', $pick('Unquoted expansion', 'Subshell state loss', 'Exit status ignored', 'Correct as written'),
                'Double quotes expand $tmpdir when the trap is INSTALLED. Here it happens to be set — but move this line above the assignment and the handler becomes rm -rf with an empty argument. Use single quotes.',
                'The trap itself is the right idea; the quoting turns a cleanup handler into a latent disaster. Single-quote trap bodies, always.',
                'Processes, signals and traps', 'signals-and-traps'],

            ['printf \'%s\\n\' "${arr[@]}"',
                'Correct as written', $pick('Correct as written', 'Array vs string', 'Unquoted expansion', 'Portability'),
                'printf reuses its format string for every remaining argument, so this prints one element per line — the canonical safe way to emit an array.',
                'echo "${arr[@]}" would collapse them onto one line and mangle values beginning with -n or -e.',
                'Functions, arrays and parameters', 'arrays-and-associative-arrays'],
        ];
    }
}
