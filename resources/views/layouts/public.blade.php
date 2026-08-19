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
    @php($containerWidth = ($wide ?? false) ? 'max-w-none' : 'max-w-5xl')

    {{-- The black bar: the one piece of hard contrast in an otherwise soft,
         pastel page. Links are pills so the hover target reads as a chip. --}}
    @php($navLink = 'rounded-full px-3 py-1.5 text-sm text-bar-fg/70 transition-colors duration-fast hover:bg-white/10 hover:text-bar-fg')

    <header class="sticky top-0 z-10 bg-bar">
        <div class="mx-auto {{ $containerWidth }} px-4 h-16 flex items-center gap-1">
            <a href="{{ route('library') }}" class="mr-4 font-display text-md font-extrabold tracking-tight text-bar-fg">
                Neural&nbsp;OS&nbsp;School
            </a>
            <a href="{{ route('library') }}" class="{{ $navLink }}">Library</a>
            <a href="{{ route('courses') }}" class="{{ $navLink }}">Courses</a>
            <a href="{{ route('gyms') }}" class="{{ $navLink }}">Gyms</a>
            <nav class="ml-auto flex items-center gap-1">
                @auth
                    <a href="{{ route('courses.mine') }}" class="{{ $navLink }}">My courses</a>
                    <a href="/dashboard" class="{{ $navLink }}">Dashboard</a>
                    @if(auth()->user()->isStaff())
                        <a href="/admin" class="{{ $navLink }}">Admin</a>
                    @endif
                @else
                    <a href="/login" class="{{ $navLink }}">Log in</a>
                    <a href="/register" class="ml-2 rounded-full bg-bar-fg px-4 py-1.5 text-sm font-semibold text-bar hover:opacity-90">Register</a>
                @endauth
                <x-theme-toggle tone="inverse" class="ml-1" />
            </nav>
        </div>
    </header>

    <main class="mx-auto {{ $containerWidth }} px-4 py-10">
        {{ $slot }}
    </main>

    <footer class="mx-auto {{ $containerWidth }} px-4 py-10 text-xs text-subtle">
        Neural OS School — a learning system built from the wiki.
    </footer>
</body>
</html>
