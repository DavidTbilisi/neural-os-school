<?php

namespace App\Livewire\Concerns;

use App\Models\Link;
use App\Models\Page;
use App\Services\Wiki\WikiRenderer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Loads a wiki Page for reading: the visibility guard, the rendered HTML, and
 * the backlinks list. Shared by the standalone reader (ShowPage) and the
 * in-course lesson reader (ShowLesson) so the two can never diverge on what
 * counts as viewable or how a page gets rendered.
 */
trait ReadsWikiPage
{
    protected function loadPage(Page $page, WikiRenderer $renderer): void
    {
        // Public + unlisted are viewable by anyone; private only as a staff preview.
        $viewable = in_array($page->visibility, [Page::VISIBILITY_PUBLIC, Page::VISIBILITY_UNLISTED], true)
            || (auth()->user()?->isStaff() ?? false);
        abort_unless($viewable, 404);

        $this->page = $page->load('domain');
        $this->html = $renderer->render($page);

        $this->backlinks = Link::where('target_page_id', $page->id)
            ->where('resolved', true)
            ->whereHas('source', fn (Builder $q) => $q->viewable())
            ->with('source:id,slug,title')
            ->get()
            ->pluck('source')
            ->filter()
            ->unique('id')
            ->sortBy('title')
            ->values();
    }
}
