<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sketchpad — {{ $course->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/sketchpad.jsx'])
</head>
<body class="h-full">
    <div class="flex h-full flex-col bg-gray-50">
        <header class="flex h-12 shrink-0 items-center gap-4 border-b border-gray-200 bg-white px-4">
            <a href="{{ route('courses.show', $course->slug) }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; {{ $course->title }}</a>
            <span class="text-sm font-semibold text-indigo-700">Sketchpad</span>
            <span class="ml-auto text-xs text-gray-400">Your private whiteboard for this course</span>
        </header>

        <main class="min-h-0 flex-1">
            <div id="sketchpad-root"
                 class="h-full w-full"
                 data-save-url="{{ route('courses.drawing.save', $course->slug) }}"
                 data-csrf="{{ csrf_token() }}"></div>
        </main>
    </div>

    {{-- Saved Excalidraw scene. @json hex-escapes < > & so a shape's text can't close this tag. --}}
    <script type="application/json" id="sketchpad-initial">@json($scene)</script>
</body>
</html>
