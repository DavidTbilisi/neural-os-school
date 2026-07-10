<div>
    <nav class="text-sm mb-6">
        <a href="{{ route('library') }}" class="text-indigo-700 hover:underline">&larr; Library</a>
    </nav>

    <article class="rounded-lg border border-gray-100 bg-white px-6 py-8 sm:px-10">
        <header class="mb-8 border-b border-gray-100 pb-5">
            <h1 class="text-3xl font-bold tracking-tight">{{ $page->title }}</h1>

            @if ($page->summary)
                <p class="mt-3 text-lg text-gray-700">{{ $page->summary }}</p>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                @if ($page->domain)
                    <span class="rounded bg-indigo-100 text-indigo-800 px-2 py-0.5">{{ $page->domain->name }}</span>
                @endif
                @if ($page->palace)
                    <span class="rounded bg-gray-100 text-gray-700 px-2 py-0.5">{{ $page->palace }}</span>
                @endif
                @if ($page->level)
                    <span class="rounded bg-gray-100 text-gray-700 px-2 py-0.5">Level {{ $page->level }}</span>
                @endif
                @if ($page->last_updated)
                    <span class="text-gray-400">updated {{ $page->last_updated->format('Y-m-d') }}</span>
                @endif
                @unless ($page->isPublished())
                    <span class="rounded bg-amber-100 text-amber-800 px-2 py-0.5">{{ ucfirst($page->visibility) }} — staff preview</span>
                @endunless
            </div>
        </header>

        <div class="prose prose-indigo max-w-none prose-headings:scroll-mt-20 prose-a:text-indigo-700">
            {!! $html !!}
        </div>
    </article>

    @if ($backlinks->isNotEmpty())
        <aside class="mt-10 border-t border-gray-200 pt-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">
                Linked from ({{ $backlinks->count() }})
            </h2>
            <ul class="grid gap-2 sm:grid-cols-2">
                @foreach ($backlinks as $b)
                    <li>
                        <a href="{{ route('wiki.show', $b->slug) }}" class="text-indigo-700 hover:underline">{{ $b->title }}</a>
                    </li>
                @endforeach
            </ul>
        </aside>
    @endif
</div>
