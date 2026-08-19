<div>
    <div class="mb-6">
        <h1 class="font-display text-3xl font-bold tracking-tight text-fg">Gyms</h1>
        <p class="text-sm text-muted mt-1">Timed recognition drills — train the reflex, measure accuracy + speed. {{ $gyms->count() }} available.</p>
    </div>

    @if ($gyms->isEmpty())
        <div class="rounded-lg border border-border bg-surface px-4 py-10 text-center text-muted">No gyms published yet.</div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($gyms as $gym)
                @php($hue = \App\Support\Palette::nth($gym->id))
                <a href="{{ route('gyms.play', $gym->slug) }}"
                   class="block overflow-hidden rounded-lg border border-border bg-surface transition hover:shadow-md">
                    <div class="{{ $hue['fill'] }} px-4 pb-4 pt-4">
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="font-display text-lg font-bold {{ $hue['text'] }}">{{ $gym->title }}</h2>
                            <span class="shrink-0 rounded-full bg-surface/70 px-2.5 py-1 text-xs font-semibold {{ $hue['text'] }}">{{ ucfirst($gym->mode) }}</span>
                        </div>
                        @if ($gym->subtitle)
                            <p class="mt-1 text-sm opacity-80 {{ $hue['text'] }}">{{ $gym->subtitle }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2 px-4 pb-4 pt-3 text-xs text-muted">
                        @if ($gym->domain)
                            <span class="whitespace-nowrap rounded-full px-2.5 py-1 {{ \App\Support\Palette::chip($gym->domain) }}">{{ $gym->domain->name }}</span>
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
