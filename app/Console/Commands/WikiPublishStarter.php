<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class WikiPublishStarter extends Command
{
    protected $signature = 'wiki:publish-starter {--dry : Show what would be published without changing anything}';

    protected $description = 'Publish a curated starter set of non-personal, technical pages to the public site.';

    /**
     * Deliberately conservative: only pure methodology/technical folders. Personal
     * domains (health, family, relational, spirituality, wealth, career, etc.) are
     * NOT published automatically — promote those by hand in the admin if desired.
     */
    private const SAFE_DIRS = [
        'learning-systems',
        'encoders',
        'cross-cutting',
        'problem-solving',
        'logic',
        'programming',
        'cybersec',
        'systems-thinking',
        'graph-theory',
        'networking',
        'math',
        'french-song', // staged from tools/french-music-drill by sync-content.sh (own lyrics, no scraped content)
    ];

    public function handle(): int
    {
        $query = Page::query()
            ->where('is_meta', false)
            ->where('visibility', Page::VISIBILITY_PRIVATE)
            ->where(function (Builder $q) {
                foreach (self::SAFE_DIRS as $dir) {
                    $q->orWhere('rel_path', 'like', $dir.'/%');
                }
            });

        $rows = (clone $query)
            ->selectRaw("substr(rel_path, 1, instr(rel_path, '/') - 1) as dir, count(*) as n")
            ->groupBy('dir')
            ->orderByDesc('n')
            ->get();

        $this->table(['folder', 'pages'], $rows->map(fn ($r) => [$r->dir, $r->n])->all());
        $total = (clone $query)->count();

        if ($this->option('dry')) {
            $this->info("Dry run: {$total} private pages would become public.");

            return self::SUCCESS;
        }

        $query->update(['visibility' => Page::VISIBILITY_PUBLIC]);
        $this->info("Published {$total} pages. Total public now: ".Page::public()->count());

        return self::SUCCESS;
    }
}
