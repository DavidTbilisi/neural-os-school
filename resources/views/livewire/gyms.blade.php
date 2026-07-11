<div>
    <div class="mb-6">
        <h1 class="font-serif text-3xl font-semibold tracking-tight text-fg">Gyms</h1>
        <p class="text-sm text-muted mt-1">Timed recognition drills — train the reflex, measure accuracy + speed. {{ $gyms->count() }} available.</p>
    </div>

    @if ($gyms->isEmpty())
        <div class="rounded-lg border border-border bg-surface px-4 py-10 text-center text-muted">No gyms published yet.</div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($gyms as $gym)
                <a href="{{ route('gyms.play', $gym->slug) }}"
                   class="block rounded-lg border border-border bg-surface p-4 hover:border-primary hover:shadow-sm transition">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-serif text-lg font-semibold text-fg">{{ $gym->title }}</h2>
                        <span class="shrink-0 text-xs rounded-full bg-surface-sunken text-muted px-2 py-0.5">{{ ucfirst($gym->mode) }}</span>
                    </div>
                    @if ($gym->subtitle)
                        <p class="mt-1 text-sm text-muted">{{ $gym->subtitle }}</p>
                    @endif
                    <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                        @if ($gym->domain)
                            <span class="rounded bg-primary-subtle text-primary-subtle-fg px-1.5 py-0.5">{{ $gym->domain->name }}</span>
                        @endif
                        <span>{{ $gym->items_count }} items</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $gym->timer_seconds }}s / round</span>
                        @if ($gym->course)
                            <span aria-hidden="true">·</span>
                            <span>Practice for {{ $gym->course->title }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
