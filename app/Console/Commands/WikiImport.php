<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Services\Wiki\WikiParser;
use App\Support\Slug;
use Database\Seeders\WikiReferenceSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class WikiImport extends Command
{
    protected $signature = 'wiki:import {--prune : Delete pages whose markdown no longer exists in the snapshot}';

    protected $description = 'Import the markdown wiki snapshot (content/wiki) into the database.';

    public function handle(WikiParser $parser): int
    {
        $contentPath = config('wiki.content_path');
        if (! File::isDirectory($contentPath)) {
            $this->error("Snapshot not found at {$contentPath}. Run ./sync-content.sh first.");

            return self::FAILURE;
        }

        $this->info('Seeding reference tables (domains/palaces/levels)…');
        (new WikiReferenceSeeder)->run();

        $parsed = $this->parsePages($parser, $contentPath);
        $this->line('  parsed '.count($parsed).' pages');

        $slugMap = $this->upsertPages($parsed);
        $this->line('  pages in DB: '.Page::count());

        $this->importLinks($parsed, $slugMap);
        $this->updateLinkCounts();

        $this->importMarks($parsed, $slugMap);
        $glossary = $this->importGlossary($contentPath, $slugMap);
        [$unlocks, $pageUnlocks] = $this->importUnlocks($contentPath, $slugMap);

        if ($this->option('prune')) {
            $this->prune(array_keys($slugMap), $parsed);
        }

        $this->report($slugMap, $glossary, $unlocks, $pageUnlocks);

        return self::SUCCESS;
    }

    /** @return array<string, array> slug => parsed page */
    private function parsePages(WikiParser $parser, string $contentPath): array
    {
        $excludeDirs = config('wiki.exclude_dirs', []);
        $pages = [];
        $collisions = 0;

        foreach (File::allFiles($contentPath) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $relPath = ltrim(str_replace($contentPath, '', $file->getPathname()), '/');
            $topDir = explode('/', $relPath)[0];
            if (in_array($topDir, $excludeDirs, true)) {
                continue;
            }

            $slug = Slug::make($file->getFilename());
            if ($slug === '') {
                continue;
            }
            if (isset($pages[$slug])) {
                $collisions++;

                continue; // keep first, matching the wiki's shortest-path assumption
            }

            $data = $parser->parse(File::get($file->getPathname()), $relPath);
            $data['slug'] = $slug;
            $pages[$slug] = $data;
        }

        if ($collisions > 0) {
            $this->warn("  {$collisions} slug collisions skipped (duplicate filenames)");
        }

        return $pages;
    }

    /** @return array<string,int> slug => page id */
    private function upsertPages(array $parsed): array
    {
        $metaSlugs = config('wiki.meta_slugs', []);
        $now = Carbon::now();
        $rows = [];

        foreach ($parsed as $slug => $p) {
            $axes = $this->normalizeAxes($p['frontmatter']);
            $rows[] = [
                'slug' => $slug,
                'title' => mb_substr($p['title'], 0, 255),
                'rel_path' => $p['rel_path'],
                'palace' => $axes['palace'],
                'level' => $axes['level'],
                'domain_id' => $axes['domainId'],
                'room' => $axes['room'],
                'para' => $axes['para'],
                'summary' => $p['summary'],
                'sources' => $p['sources'],
                'last_updated' => $p['last_updated'],
                'body_md' => $p['body'],
                'visibility' => Page::VISIBILITY_PRIVATE, // only applied to NEW rows
                'is_meta' => in_array($slug, $metaSlugs, true),
                'axis_clean' => $axes['axisClean'],
                'word_count' => $p['word_count'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Update everything EXCEPT visibility + created_at so an admin's publish
        // decisions survive re-imports; new pages insert as private.
        $update = ['title', 'rel_path', 'palace', 'level', 'domain_id', 'room', 'para',
            'summary', 'sources', 'last_updated', 'body_md', 'is_meta', 'axis_clean',
            'word_count', 'updated_at'];

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('pages')->upsert($chunk, ['slug'], $update);
        }

        return DB::table('pages')->pluck('id', 'slug')->all();
    }

    private function importLinks(array $parsed, array $slugMap): void
    {
        DB::table('links')->truncate();

        $rows = [];
        foreach ($parsed as $slug => $p) {
            $sourceId = $slugMap[$slug];
            foreach ($p['links'] as $link) {
                $targetId = $slugMap[$link['target_slug']] ?? null;
                $rows[] = [
                    'source_page_id' => $sourceId,
                    'source_slug' => $slug,
                    'target_slug' => $link['target_slug'],
                    'target_page_id' => $targetId,
                    'display_text' => $link['display_text'] ? mb_substr($link['display_text'], 0, 255) : null,
                    'anchor' => $link['anchor'] ? mb_substr($link['anchor'], 0, 255) : null,
                    'resolved' => $targetId !== null,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('links')->insert($chunk);
        }
    }

    private function updateLinkCounts(): void
    {
        DB::statement('UPDATE pages SET outbound_count = (SELECT COUNT(*) FROM links WHERE links.source_page_id = pages.id)');
        DB::statement('UPDATE pages SET inbound_count = (SELECT COUNT(*) FROM links WHERE links.target_page_id = pages.id)');
    }

    private function importMarks(array $parsed, array $slugMap): void
    {
        DB::table('marks')->truncate();

        $rows = [];
        foreach ($parsed as $slug => $p) {
            foreach ($p['marks'] as $mark) {
                $rows[] = [
                    'page_id' => $slugMap[$slug],
                    'page_slug' => $slug,
                    'sigil' => mb_substr($mark['sigil'], 0, 255),
                    'text' => $mark['text'],
                    'para_index' => $mark['para_index'],
                    'route' => null,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('marks')->insert($chunk);
        }
    }

    private function importGlossary(string $contentPath, array $slugMap): int
    {
        DB::table('glossary_terms')->truncate();
        $rows = [];

        foreach ($this->tsvRows("{$contentPath}/_meta/glossary-registry.tsv", ['term', 'owner', 'section']) as $r) {
            $ownerSlug = $r['owner'] !== '' ? $r['owner'] : null;
            $rows[] = [
                'term' => mb_substr($r['term'], 0, 255),
                'owner_slug' => $ownerSlug,
                'owner_page_id' => $ownerSlug ? ($slugMap[$ownerSlug] ?? null) : null,
                'section' => $r['section'] !== '' ? mb_substr($r['section'], 0, 255) : null,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('glossary_terms')->insert($chunk);
        }

        return count($rows);
    }

    /** @return array{0:int,1:int} [unlocks, pageUnlocks] */
    private function importUnlocks(string $contentPath, array $slugMap): array
    {
        DB::table('unlocks')->truncate();
        DB::table('page_unlocks')->truncate();

        $unlockRows = [];
        foreach ($this->tsvRows("{$contentPath}/_meta/unlocks-registry.tsv", ['status', 'name', 'links']) as $r) {
            $components = $r['links'] !== '' ? array_map('trim', explode(',', $r['links'])) : [];
            $unlockRows[] = [
                'slug' => Slug::make($r['name']),
                'name' => $r['name'],
                'status' => $r['status'] !== '' ? $r['status'] : null,
                'components' => json_encode($components),
            ];
        }
        foreach (array_chunk($unlockRows, 500) as $chunk) {
            DB::table('unlocks')->insert($chunk);
        }

        $pageUnlockRows = [];
        foreach ($this->tsvRows("{$contentPath}/_meta/page-unlocks.tsv", ['page_slug', 'unlock_slug', 'status', 'role']) as $r) {
            $pageUnlockRows[] = [
                'page_id' => $slugMap[$r['page_slug']] ?? null,
                'page_slug' => $r['page_slug'],
                'unlock_slug' => $r['unlock_slug'],
                'status' => $r['status'] !== '' ? $r['status'] : null,
                'role' => $r['role'] !== '' ? $r['role'] : null,
            ];
        }
        foreach (array_chunk($pageUnlockRows, 500) as $chunk) {
            DB::table('page_unlocks')->insert($chunk);
        }

        return [count($unlockRows), count($pageUnlockRows)];
    }

    /**
     * Read a generated TSV: skip `#` comment lines and the header row, then map
     * each remaining tab-split row onto $columns.
     *
     * @return list<array<string,string>>
     */
    private function tsvRows(string $path, array $columns): array
    {
        if (! File::exists($path)) {
            $this->warn('  missing '.basename($path).' — skipped');

            return [];
        }

        $rows = [];
        $headerSeen = false;
        foreach (preg_split('/\R/', File::get($path)) as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! $headerSeen) {
                $headerSeen = true; // first non-comment line is the header

                continue;
            }

            $parts = explode("\t", $line);
            $row = [];
            foreach ($columns as $i => $col) {
                $row[$col] = trim($parts[$i] ?? '');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function normalizeAxes(array $fm): array
    {
        $palaces = config('wiki.palaces');

        $asInt = fn ($v) => is_int($v) ? $v : (is_string($v) && ctype_digit($v) ? (int) $v : null);
        $inRange = fn (?int $v) => $v !== null && $v >= 1 && $v <= 10 ? $v : null;

        $domainId = $inRange($asInt($fm['domain'] ?? null));
        $level = $inRange($asInt($fm['level'] ?? null));

        $palace = null;
        if (is_string($fm['palace'] ?? null)) {
            $pk = strtolower(trim($fm['palace']));
            $palace = in_array($pk, $palaces, true) ? $pk : null;
        }

        $room = $asInt($fm['room'] ?? null);

        $para = null;
        if (is_string($fm['para'] ?? null)) {
            $pa = strtolower(trim($fm['para']));
            $para = in_array($pa, ['project', 'area', 'resource', 'archive'], true) ? $pa : null;
        }

        return [
            'domainId' => $domainId,
            'palace' => $palace,
            'level' => $level,
            'room' => $room,
            'para' => $para,
            'axisClean' => $domainId !== null && $palace !== null && $level !== null,
        ];
    }

    private function prune(array $seenSlugs, array $parsed): void
    {
        $stale = DB::table('pages')->whereNotIn('slug', $seenSlugs)->pluck('slug');
        if ($stale->isNotEmpty()) {
            DB::table('pages')->whereIn('slug', $stale->all())->delete();
            $this->warn('  pruned '.$stale->count().' stale pages');
        }
    }

    private function report(array $slugMap, int $glossary, int $unlocks, int $pageUnlocks): void
    {
        $broken = DB::table('links')->where('resolved', false)->count();
        $brokenPages = DB::table('links')->where('resolved', false)->distinct()->count('source_page_id');
        $orphans = DB::table('pages')->where('inbound_count', 0)->where('is_meta', false)->count();
        $unclean = DB::table('pages')->where('axis_clean', false)->count();

        $this->newLine();
        $this->info('Import complete:');
        $this->table(['metric', 'count'], [
            ['pages', count($slugMap)],
            ['links (edges)', DB::table('links')->count()],
            ['  broken links', $broken],
            ['  pages w/ broken links', $brokenPages],
            ['orphan pages (0 inbound)', $orphans],
            ['axis-unclean pages', $unclean],
            ['marks', DB::table('marks')->count()],
            ['glossary terms', $glossary],
            ['unlocks', $unlocks],
            ['page-unlock rows', $pageUnlocks],
        ]);
    }
}
