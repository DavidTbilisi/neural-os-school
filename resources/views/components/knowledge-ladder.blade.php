@props([
    'ladder',            // KnowledgeLadder::all() — 10 rungs, index = level
    'current' => null,   // achieved level (0-9), or null for an overview
    'ceiling' => 9,      // highest rung this gym can certify; higher rungs shown as "beyond"
])

{{-- The Red Queen Knowledge Ladder as a 0–9 strip. Rungs up to `current` are
     filled; the current rung is emphasized; rungs above `ceiling` are dashed
     (out of a timed drill's reach). --}}
<div {{ $attributes->merge(['class' => 'flex items-end gap-1']) }}
     role="img"
     aria-label="Knowledge ladder — {{ $current !== null ? 'level '.$current.' of 9' : 'levels 0 to 9' }}">
    @foreach ($ladder as $rung)
        @php($lvl = $rung['level'])
        @php($reached = $current !== null && $lvl <= $current)
        @php($isCurrent = $lvl === $current)
        @php($beyond = $lvl > $ceiling)
        <div class="flex-1 min-w-0 text-center" title="{{ $lvl }} · {{ $rung['name'] }} — {{ $rung['standard'] }}">
            <div @class([
                'rounded-sm transition-colors',
                'h-2.5' => $isCurrent,
                'h-1.5' => ! $isCurrent && ! $beyond,
                'h-1.5 border border-dashed border-border bg-transparent' => $beyond,
                'bg-primary' => $reached && ! $beyond,
                'bg-surface-sunken' => ! $reached && ! $beyond,
            ])></div>
            <div @class([
                'mt-1 font-mono text-[10px] leading-none',
                'text-primary font-bold' => $isCurrent,
                'text-muted' => ! $isCurrent && $reached && ! $beyond,
                'text-subtle' => $beyond || (! $reached && ! $isCurrent),
            ])>{{ $lvl }}</div>
        </div>
    @endforeach
</div>
