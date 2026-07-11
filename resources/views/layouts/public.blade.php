<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Neural OS School' }}</title>
    @include('partials.theme-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-bg text-fg">
    <header class="border-b border-border bg-surface/90 backdrop-blur sticky top-0 z-10">
        <div class="mx-auto max-w-5xl px-4 h-14 flex items-center gap-6">
            <a href="{{ route('library') }}" class="font-semibold tracking-tight text-primary">
                Neural&nbsp;OS&nbsp;School
            </a>
            <a href="{{ route('library') }}" class="text-sm text-muted hover:text-fg">Library</a>
            <a href="{{ route('courses') }}" class="text-sm text-muted hover:text-fg">Courses</a>
            <a href="{{ route('gyms') }}" class="text-sm text-muted hover:text-fg">Gyms</a>
            <nav class="ml-auto flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ route('courses.mine') }}" class="text-muted hover:text-fg">My courses</a>
                    <a href="/dashboard" class="text-muted hover:text-fg">Dashboard</a>
                    @if(auth()->user()->isStaff())
                        <a href="/admin" class="text-muted hover:text-fg">Admin</a>
                    @endif
                @else
                    <a href="/login" class="text-muted hover:text-fg">Log in</a>
                    <a href="/register" class="rounded-md bg-primary px-3 py-1.5 text-primary-fg hover:bg-primary-hover">Register</a>
                @endauth
                <x-theme-toggle />
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-5xl px-4 py-10 text-xs text-subtle">
        Neural OS School — a learning system built from the wiki.
    </footer>
</body>
</html>
