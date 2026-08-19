{{--
    The wiki page reading column: header/chips, optional "Part of {Course}"
    banner, the rendered markdown body, representation panels, and backlinks.
    Shared verbatim by the standalone reader (show-page.blade.php) and the
    in-course lesson reader (show-lesson.blade.php) so the two can never
    render this content differently. Expects $page, $html, $backlinks, and
    optionally $reps / $courses (courses defaults to empty — the in-course
    reader doesn't need to tell you you're part of the course you're already
    reading inside of).
--}}
@php($courses = $courses ?? collect())

<article class="rounded-lg border border-border bg-surface px-6 py-8 sm:px-10">
    <header class="mb-8 border-b border-border pb-5">
        <h1 class="font-display text-4xl font-bold tracking-tight text-fg">{{ $page->title }}</h1>

        @if ($page->summary)
            <p class="mt-3 font-display text-lg text-muted">{{ $page->summary }}</p>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
            @if ($page->domain)
                <span class="whitespace-nowrap rounded-full bg-primary-subtle px-2.5 py-1 text-primary-subtle-fg">{{ $page->domain->name }}</span>
            @endif
            @if ($page->palace)
                <span class="rounded bg-surface-sunken text-muted px-2 py-0.5">{{ $page->palace }}</span>
            @endif
            @if ($page->level)
                <span class="rounded bg-surface-sunken text-muted px-2 py-0.5">Level {{ $page->level }}</span>
            @endif
            @if ($page->last_updated)
                <span class="text-subtle">updated {{ $page->last_updated->format('Y-m-d') }}</span>
            @endif
            @unless ($page->isPublished())
                <span class="rounded bg-warning-subtle text-warning-fg px-2 py-0.5">{{ ucfirst($page->visibility) }} — staff preview</span>
            @endunless
        </div>
    </header>

    @if ($courses->isNotEmpty())
        <div class="mb-6 rounded-lg bg-primary-subtle/40 border border-border px-4 py-3 text-sm">
            <span class="text-muted">Part of </span>
            @foreach ($courses as $c)
                <a href="{{ route('courses.show', $c->slug) }}" class="font-medium text-primary hover:underline">{{ $c->title }}</a>@unless ($loop->last),@endunless
            @endforeach
        </div>
    @endif

    @include('livewire.partials.page-audio')

    {{--
        The reading column. `prose` handles element structure; the token-mapped
        --tw-prose-* variables retint it to ink / grape, and because each
        resolves to a CSS variable that flips on `.dark`, dark mode needs no
        `dark:prose-invert`. `font-display` is the one sans voice; code stays mono.
    --}}
    <div class="prose prose-lg max-w-none font-display
                prose-headings:font-display prose-headings:scroll-mt-20
                prose-code:font-mono prose-code:font-normal prose-code:rounded prose-code:bg-surface-sunken prose-code:px-1 prose-code:py-0.5
                prose-code:before:content-none prose-code:after:content-none
                prose-pre:font-mono prose-pre:text-sm prose-pre:leading-snug
                prose-pre:border prose-pre:border-border prose-pre:shadow-sm
                [--tw-prose-body:rgb(var(--color-fg))]
                [--tw-prose-headings:rgb(var(--color-fg))]
                [--tw-prose-links:rgb(var(--color-fg-link))]
                [--tw-prose-bold:rgb(var(--color-fg))]
                [--tw-prose-code:rgb(var(--color-fg))]
                [--tw-prose-pre-bg:rgb(var(--color-surface-sunken))]
                [--tw-prose-pre-code:rgb(var(--color-fg))]
                [--tw-prose-quotes:rgb(var(--color-fg-muted))]
                [--tw-prose-quote-borders:rgb(var(--color-primary))]
                [--tw-prose-bullets:rgb(var(--color-fg-subtle))]
                [--tw-prose-counters:rgb(var(--color-fg-subtle))]
                [--tw-prose-hr:rgb(var(--color-border))]
                [--tw-prose-captions:rgb(var(--color-fg-muted))]
                [--tw-prose-th-borders:rgb(var(--color-border))]
                [--tw-prose-td-borders:rgb(var(--color-border-subtle))]">
        {!! $html !!}
    </div>
</article>

@isset($reps)
    @include('livewire.partials.representations')
@endisset

@if ($backlinks->isNotEmpty())
    <aside class="mt-10 border-t border-border pt-6">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-muted mb-3">
            Linked from ({{ $backlinks->count() }})
        </h2>
        <ul class="grid gap-2 sm:grid-cols-2">
            @foreach ($backlinks as $b)
                <li>
                    <a href="{{ route('wiki.show', $b->slug) }}" class="text-primary hover:underline">{{ $b->title }}</a>
                </li>
            @endforeach
        </ul>
    </aside>
@endif
