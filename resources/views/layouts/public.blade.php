<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Neural OS School' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900">
    <header class="border-b border-gray-200 bg-white/90 backdrop-blur sticky top-0 z-10">
        <div class="mx-auto max-w-5xl px-4 h-14 flex items-center gap-6">
            <a href="{{ route('library') }}" class="font-semibold tracking-tight text-indigo-700">
                Neural&nbsp;OS&nbsp;School
            </a>
            <a href="{{ route('library') }}" class="text-sm text-gray-600 hover:text-gray-900">Library</a>
            <a href="{{ route('courses') }}" class="text-sm text-gray-600 hover:text-gray-900">Courses</a>
            <a href="{{ route('gyms') }}" class="text-sm text-gray-600 hover:text-gray-900">Gyms</a>
            <nav class="ml-auto flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ route('courses.mine') }}" class="text-gray-600 hover:text-gray-900">My courses</a>
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                    @if(auth()->user()->isStaff())
                        <a href="/admin" class="text-gray-600 hover:text-gray-900">Admin</a>
                    @endif
                @else
                    <a href="/login" class="text-gray-600 hover:text-gray-900">Log in</a>
                    <a href="/register" class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-500">Register</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-5xl px-4 py-10 text-xs text-gray-400">
        Neural OS School — a learning system built from the wiki.
    </footer>
</body>
</html>
