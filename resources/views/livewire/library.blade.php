<div>
    <div class="mb-6">
        <h1 class="font-serif text-3xl font-semibold tracking-tight text-fg">Library</h1>
        <p class="text-sm text-muted mt-1">{{ number_format($total) }} published pages</p>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search titles and summaries…"
            class="w-full rounded-md border-border bg-surface text-fg placeholder:text-subtle shadow-sm focus:border-primary focus:ring-primary"
        >
        <select wire:model.live="domain" class="rounded-md border-border bg-surface text-fg shadow-sm focus:border-primary focus:ring-primary sm:w-64">
            <option value="">All domains</option>
            @foreach ($domains as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <ul class="divide-y divide-border-subtle rounded-lg border border-border bg-surface">
        @forelse ($pages as $page)
            <li class="px-4 py-3 hover:bg-surface-sunken transition-colors duration-fast">
                <a href="{{ route('wiki.show', $page->slug) }}" class="font-serif text-lg text-fg hover:text-primary hover:underline">
                    {{ $page->title }}
                </a>
                @if ($page->domain)
                    <span class="ml-2 align-middle text-xs rounded bg-primary-subtle text-primary-subtle-fg px-1.5 py-0.5">{{ $page->domain->name }}</span>
                @endif
                @if ($page->summary)
                    <p class="text-sm text-muted mt-1">{{ Str::limit($page->summary, 200) }}</p>
                @endif
            </li>
        @empty
            <li class="px-4 py-10 text-center text-muted">No published pages match your search.</li>
        @endforelse
    </ul>

    <div class="mt-6">
        {{ $pages->links() }}
    </div>
</div>
