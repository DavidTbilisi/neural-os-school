<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        {{-- Design-system fonts + no-flash theme init --}}
        @include('partials.theme-head')

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-fg antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-bg">
            <div>
                <a href="/" wire:navigate>
                    <x-application-logo class="w-20 h-20 fill-current text-muted" />
                </a>
            </div>

            <div class="mt-6 w-full overflow-hidden border border-border bg-surface px-8 py-8 shadow-md sm:max-w-md sm:rounded-xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
