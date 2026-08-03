{{--
    Audio tracks for pages that have them (French song units — resolved by
    App\Support\PageAudio, which returns [] for everything else). Sits between
    the page header and the reading column: these are listen-first lessons.
--}}
@php($tracks = \App\Support\PageAudio::for($page))
@if ($tracks !== [])
    <div class="mb-6 space-y-3 rounded-md border border-border bg-surface-sunken px-4 py-3">
        @foreach ($tracks as $track)
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-3">
                <span class="shrink-0 text-sm text-muted sm:w-64">{{ $track['label'] }}</span>
                <audio controls preload="none" src="{{ $track['url'] }}" class="h-9 w-full max-w-md"></audio>
            </div>
        @endforeach
    </div>
@endif
