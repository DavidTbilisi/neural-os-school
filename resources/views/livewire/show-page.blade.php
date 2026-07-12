<div class="mx-auto max-w-4xl">
    <nav class="text-sm mb-6">
        <a href="{{ route('library') }}" class="text-muted hover:text-primary hover:underline">&larr; Library</a>
    </nav>

    @include('livewire.partials.page-body', ['courses' => $courses])
</div>
