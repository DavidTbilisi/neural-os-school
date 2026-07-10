<?php

namespace App\Filament\Widgets;

use App\Models\ScoreLens;
use Filament\Widgets\ChartWidget;

class DomainScoresChart extends ChartWidget
{
    protected static ?string $heading = 'Complexity by domain';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $rows = ScoreLens::where('lens_type', 'domain')
            ->orderByDesc('complexity')
            ->get();

        $domains = config('wiki.domains');

        return [
            'datasets' => [[
                'label' => 'Complexity',
                'data' => $rows->pluck('complexity')->map(fn ($v) => round($v))->all(),
                'backgroundColor' => '#6366f1',
                'borderRadius' => 4,
            ]],
            'labels' => $rows->map(fn ($r) => $domains[$r->lens_key]
                ?? ($r->lens_key === '0' ? 'Uncategorized' : "Domain {$r->lens_key}"))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
