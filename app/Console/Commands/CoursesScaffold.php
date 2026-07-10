<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Page;
use App\Support\Slug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed a DRAFT course from a roadmap page. Reads the (already-imported) page body,
 * treats each `##` heading as a module and the [[links]] beneath it as lessons
 * (resolving to existing pages). The result is intentionally rough — admins prune,
 * reorder, and publish it in Filament. "Scaffold from roadmaps, then curate."
 */
class CoursesScaffold extends Command
{
    protected $signature = 'courses:scaffold
        {roadmap? : slug of a roadmap page (omit to list candidates)}
        {--force : rebuild modules/lessons if the course already exists (drops learner progress)}';

    protected $description = 'Scaffold a draft course (modules + lessons) from a wiki roadmap page.';

    public function handle(): int
    {
        $slug = $this->argument('roadmap');

        if (! $slug) {
            return $this->listCandidates();
        }

        $roadmap = Page::where('slug', $slug)->first();
        if (! $roadmap) {
            $this->error("No page with slug '{$slug}'. Run without an argument to list roadmap candidates.");

            return self::FAILURE;
        }

        $existing = Course::where('slug', $slug)->first();
        if ($existing && ! $this->option('force')) {
            $this->error("Course '{$slug}' already exists. Re-run with --force to rebuild its modules/lessons.");

            return self::FAILURE;
        }

        $pages = Page::get(['id', 'slug', 'title'])->keyBy('slug');
        $parsed = $this->parseModules($roadmap->body_md, $pages);

        if ($parsed === []) {
            $this->warn('No headings with resolvable [[links]] found — nothing to scaffold.');

            return self::FAILURE;
        }

        [$modules, $lessons, $unresolved] = DB::transaction(
            fn () => $this->build($roadmap, $existing, $parsed)
        );

        $this->info(($existing ? 'Rebuilt' : 'Scaffolded')." draft course '{$slug}':");
        $this->table(['metric', 'count'], [
            ['modules', $modules],
            ['lessons', $lessons],
            ['unresolved [[links]] skipped', $unresolved],
        ]);
        $this->line('  Curate & publish it at /admin → Learning → Courses.');

        return self::SUCCESS;
    }

    /**
     * Parse the body into ordered modules. A `##` (H2) line opens a module; every
     * [[link]] until the next H2 that resolves to a known page becomes a lesson.
     * Deeper headings (###) stay inside the current module. Links before the first
     * H2 (the Summary/Sources preamble) are ignored.
     *
     * @param  \Illuminate\Support\Collection<string, Page>  $pages
     * @return list<array{title:string, lessons:list<array{page_id:int, title:string}>, unresolved:int}>
     */
    private function parseModules(string $body, $pages): array
    {
        $modules = [];
        $current = null;

        foreach (preg_split('/\R/', $body) as $line) {
            if (preg_match('/^##\s+(?!#)(.+?)\s*$/', $line, $m)) {
                if ($current !== null) {
                    $modules[] = $current;
                }
                $current = ['title' => $this->cleanHeading($m[1]), 'lessons' => [], 'seen' => [], 'unresolved' => 0];

                continue;
            }

            if ($current === null) {
                continue; // preamble — before the first module heading
            }

            if (! preg_match_all('/\[\[(.+?)\]\]/', $line, $matches)) {
                continue;
            }

            foreach ($matches[1] as $inner) {
                [$target, $display] = $this->splitLink($inner);
                if ($target === '') {
                    continue;
                }

                $page = $pages->get($target);
                if (! $page) {
                    $current['unresolved']++;

                    continue;
                }
                if (isset($current['seen'][$target])) {
                    continue; // one lesson per page per module
                }

                $current['seen'][$target] = true;
                $current['lessons'][] = [
                    'page_id' => $page->id,
                    'title' => $display ?: $page->title,
                ];
            }
        }

        if ($current !== null) {
            $modules[] = $current;
        }

        // Drop modules that yielded no lessons (prose-only headings).
        return array_values(array_filter(
            array_map(fn ($mod) => ['title' => $mod['title'], 'lessons' => $mod['lessons'], 'unresolved' => $mod['unresolved']], $modules),
            fn ($mod) => $mod['lessons'] !== [],
        ));
    }

    /**
     * Persist the parsed tree. Upserts the course row (preserving status +
     * enrollments on --force); wipes and rebuilds modules/lessons.
     *
     * @return array{0:int,1:int,2:int} [modules, lessons, unresolved]
     */
    private function build(Page $roadmap, ?Course $existing, array $parsed): array
    {
        $course = $existing ?? new Course(['slug' => $roadmap->slug, 'status' => Course::STATUS_DRAFT]);
        $course->title = $this->courseTitle($roadmap->title);
        $course->subtitle = $roadmap->summary ? Str::limit(strip_tags($roadmap->summary), 250) : null;
        $course->source_page_id = $roadmap->id;
        $course->domain_id = $roadmap->domain_id;
        $course->save();

        $course->modules()->delete(); // cascades lessons (+ their completions)

        $moduleCount = 0;
        $lessonCount = 0;
        $unresolved = 0;

        foreach ($parsed as $mi => $mod) {
            $module = $course->modules()->create([
                'title' => Str::limit($mod['title'], 200, ''),
                'sort' => $mi,
            ]);
            $moduleCount++;
            $unresolved += $mod['unresolved'];

            foreach ($mod['lessons'] as $li => $lesson) {
                Lesson::create([
                    'module_id' => $module->id,
                    'page_id' => $lesson['page_id'],
                    'title' => Str::limit($lesson['title'], 200, ''),
                    'sort' => $li,
                ]);
                $lessonCount++;
            }
        }

        return [$moduleCount, $lessonCount, $unresolved];
    }

    /** @return array{0:string, 1:?string} [target slug, display text] */
    private function splitLink(string $inner): array
    {
        $inner = str_replace(['\\|', '\\#'], ['|', '#'], trim($inner));

        $display = null;
        if (str_contains($inner, '|')) {
            [$inner, $display] = explode('|', $inner, 2);
            $display = trim($display);
        }
        if (str_contains($inner, '#')) {
            [$inner] = explode('#', $inner, 2);
        }

        return [Slug::make($inner), $display];
    }

    /** Strip markdown noise (bold, code, links) from a heading for a module title. */
    private function cleanHeading(string $h): string
    {
        $h = preg_replace('/\[\[([^\]|]+)\|([^\]]+)\]\]/', '$2', $h); // [[a|b]] -> b
        $h = preg_replace('/\[\[([^\]]+)\]\]/', '$1', $h);           // [[a]]   -> a
        $h = str_replace(['**', '`', '*'], '', $h);

        return trim($h);
    }

    private function courseTitle(string $roadmapTitle): string
    {
        return trim(preg_replace('/\s*Roadmap\s*$/i', '', $roadmapTitle)) ?: $roadmapTitle;
    }

    private function listCandidates(): int
    {
        $roadmaps = Page::where('slug', 'like', '%roadmap%')
            ->orWhere('para', 'project')
            ->orderBy('slug')
            ->get(['slug', 'title', 'para']);

        if ($roadmaps->isEmpty()) {
            $this->warn('No roadmap / project pages found. Run `wiki:import` first.');

            return self::SUCCESS;
        }

        $this->info('Roadmap candidates — run `courses:scaffold <slug>`:');
        $this->table(
            ['slug', 'title', 'para'],
            $roadmaps->map(fn ($p) => [$p->slug, Str::limit($p->title, 55), $p->para])->all()
        );

        return self::SUCCESS;
    }
}
