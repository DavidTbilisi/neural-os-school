<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-900">
                Welcome back, {{ auth()->user()->name }}.
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <a href="{{ route('library') }}"
                   class="block bg-white shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition">
                    <div class="text-lg font-semibold text-indigo-700">Library →</div>
                    <p class="text-sm text-gray-600 mt-1">Browse and search the published knowledge base.</p>
                </a>

                @if (auth()->user()->isStaff())
                    <a href="{{ url('/admin') }}"
                       class="block bg-white shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition">
                        <div class="text-lg font-semibold text-indigo-700">Admin →</div>
                        <p class="text-sm text-gray-600 mt-1">Dashboard, pages, glossary, users, and the publish gate.</p>
                    </a>
                @endif

                <a href="{{ route('profile') }}"
                   class="block bg-white shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition">
                    <div class="text-lg font-semibold text-indigo-700">Profile →</div>
                    <p class="text-sm text-gray-600 mt-1">Update your name, email, or password.</p>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
