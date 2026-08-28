<?php

use App\Support\Navigation;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * The learner's keys, top of the list = leftmost in the bar.
     *
     * @var list<string>
     */
    public array $order = [];

    /**
     * The keys still switched on. Stored inverted (as hides) so a link shipped
     * later shows up by default — see App\Support\Navigation.
     *
     * @var list<string>
     */
    public array $visible = [];

    public function mount(): void
    {
        $this->load();
    }

    public function moveUp(string $key): void
    {
        $this->move($key, -1);
    }

    public function moveDown(string $key): void
    {
        $this->move($key, 1);
    }

    public function save(): void
    {
        Navigation::save(
            Auth::user(),
            $this->order,
            array_values(array_diff($this->order, $this->visible)),
        );

        $this->load();
        $this->dispatch('nav-updated');
        $this->dispatch('nav-preferences-saved');
    }

    public function restoreDefaults(): void
    {
        Navigation::reset(Auth::user());

        $this->load();
        $this->dispatch('nav-updated');
        $this->dispatch('nav-preferences-saved');
    }

    /**
     * Read the arrangement back out of the registry rather than trusting local
     * state, so what the form shows is exactly what the bar will render.
     */
    private function load(): void
    {
        $rows = Navigation::rowsFor(Auth::user());

        $this->order = array_column($rows, 'key');
        $this->visible = array_column(array_filter($rows, fn (array $row): bool => ! $row['hidden']), 'key');
    }

    private function move(string $key, int $step): void
    {
        $from = array_search($key, $this->order, true);

        if ($from === false) {
            return;
        }

        $to = $from + $step;

        if ($to < 0 || $to >= count($this->order)) {
            return;
        }

        [$this->order[$from], $this->order[$to]] = [$this->order[$to], $this->order[$from]];
    }

    public function with(): array
    {
        return ['rows' => Navigation::rowsFor(Auth::user())];
    }
}; ?>

@php
    // The rows are re-sorted by the working copy, not by what is saved, so the
    // list moves under the arrows before anything is written.
    $byKey = collect($rows)->keyBy('key');
    $arranged = collect($order)->map(fn (string $key) => $byKey->get($key))->filter();
    $last = $arranged->count() - 1;
    $arrow = 'flex h-7 w-7 items-center justify-center rounded-full border border-border text-fg transition-colors duration-fast hover:bg-surface-sunken disabled:opacity-30 disabled:hover:bg-transparent';
@endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-fg">
            {{ __('Navigation') }}
        </h2>

        <p class="mt-1 text-sm text-muted">
            {{ __('Arrange your own top bar. The top of this list is the leftmost link; switch off the ones you never use.') }}
        </p>
    </header>

    <form wire:submit="save" class="mt-6 space-y-6">
        <ul class="space-y-2">
            @foreach ($arranged as $index => $item)
                <li wire:key="nav-{{ $item['key'] }}"
                    class="flex items-center gap-3 rounded-full border border-border bg-surface-sunken px-3 py-2">
                    <div class="flex items-center gap-1">
                        <button type="button"
                                class="{{ $arrow }}"
                                wire:click="moveUp('{{ $item['key'] }}')"
                                @disabled($index === 0)
                                aria-label="{{ __('Move :label earlier', ['label' => $item['label']]) }}">
                            &uarr;
                        </button>
                        <button type="button"
                                class="{{ $arrow }}"
                                wire:click="moveDown('{{ $item['key'] }}')"
                                @disabled($index === $last)
                                aria-label="{{ __('Move :label later', ['label' => $item['label']]) }}">
                            &darr;
                        </button>
                    </div>

                    <span class="flex-1 text-sm font-medium text-fg">{{ __($item['label']) }}</span>

                    @if ($item['locked'])
                        <span class="text-xs text-muted">{{ __('always shown') }}</span>
                    @else
                        <label class="flex items-center gap-2 text-sm text-muted">
                            <input type="checkbox"
                                   value="{{ $item['key'] }}"
                                   wire:model="visible"
                                   class="rounded border-border bg-surface text-primary focus:ring-ring">
                            {{ __('Show') }}
                        </label>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-secondary-button wire:click="restoreDefaults">{{ __('Reset') }}</x-secondary-button>

            <x-action-message class="me-3" on="nav-preferences-saved">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
