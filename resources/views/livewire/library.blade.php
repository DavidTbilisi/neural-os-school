<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight">Library</h1>
        <p class="text-sm text-gray-500 mt-1">{{ number_format($total) }} published pages</p>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search titles and summaries…"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        >
        <select wire:model.live="domain" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-64">
            <option value="">All domains</option>
            @foreach ($domains as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <ul class="divide-y divide-gray-100 rounded-lg border border-gray-100 bg-white">
        @forelse ($pages as $page)
            <li class="px-4 py-3 hover:bg-gray-50">
                <a href="{{ route('wiki.show', $page->slug) }}" class="text-indigo-700 font-medium hover:underline">
                    {{ $page->title }}
                </a>
                @if ($page->domain)
                    <span class="ml-2 align-middle text-xs rounded bg-indigo-50 text-indigo-700 px-1.5 py-0.5">{{ $page->domain->name }}</span>
                @endif
                @if ($page->summary)
                    <p class="text-sm text-gray-600 mt-1">{{ Str::limit($page->summary, 200) }}</p>
                @endif
            </li>
        @empty
            <li class="px-4 py-10 text-center text-gray-500">No published pages match your search.</li>
        @endforelse
    </ul>

    <div class="mt-6">
        {{ $pages->links() }}
    </div>
</div>
