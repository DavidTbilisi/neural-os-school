<?php

namespace App\Livewire;

use App\Livewire\Concerns\ReadsWikiPage;
use App\Models\Course;
use App\Models\Page;
use App\Services\Wiki\RepresentationBuilder;
use App\Services\Wiki\WikiRenderer;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class ShowPage extends Component
{
    use ReadsWikiPage;

    public Page $page;

    public string $html;

    /** @var \Illuminate\Support\Collection<int,\App\Models\Page> */
    public $backlinks;

    /** @var \Illuminate\Support\Collection<int,\App\Models\Course> */
    public $courses;

    public function mount(string $slug, WikiRenderer $renderer): void
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        $this->loadPage($page, $renderer);

        // Published courses that use this page as a lesson (wiki ↔ course cross-link).
        $this->courses = Course::published()
            ->whereHas('lessons', fn (Builder $q) => $q->where('lessons.page_id', $page->id))
            ->orderBy('title')
            ->get(['id', 'slug', 'title']);
    }

    public function render(RepresentationBuilder $representations)
    {
        // Derived + static per page, so it is built here (not held in Livewire
        // state) to keep the large echarts option out of every request snapshot.
        return view('livewire.show-page', [
            'reps' => $representations->for($this->page),
        ]);
    }
}
