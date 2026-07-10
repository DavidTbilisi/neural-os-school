<?php

namespace App\Livewire;

use App\Models\Link;
use App\Models\Page;
use App\Services\Wiki\WikiRenderer;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class ShowPage extends Component
{
    public Page $page;

    public string $html;

    /** @var \Illuminate\Support\Collection<int,\App\Models\Page> */
    public $backlinks;

    public function mount(string $slug, WikiRenderer $renderer): void
    {
        $page = Page::where('slug', $slug)->firstOrFail();

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

    public function render()
    {
        return view('livewire.show-page');
    }
}
