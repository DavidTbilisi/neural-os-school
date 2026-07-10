<?php

namespace App\Livewire;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.public')]
class Library extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $domain = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDomain(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $pages = Page::public()
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('summary', 'like', "%{$this->search}%")))
            ->when($this->domain !== '', fn (Builder $q) => $q->where('domain_id', $this->domain))
            ->orderBy('title')
            ->paginate(20);

        return view('livewire.library', [
            'pages' => $pages,
            'domains' => config('wiki.domains'),
            'total' => Page::public()->count(),
        ]);
    }
}
