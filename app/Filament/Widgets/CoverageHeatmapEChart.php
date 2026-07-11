<?php

namespace App\Filament\Widgets;

use App\Models\Page;

/**
 * Page coverage as a palace × domain heatmap, rendered with Apache ECharts.
 *
 * Demonstrates a chart type Filament's built-in Chart.js ChartWidget can't do.
 * Same data as the CSS-grid CoverageHeatmap widget, drawn as a real heatmap
 * with a calculable colour scale.
 */
class CoverageHeatmapEChart extends EChartWidget
{
    protected static ?string $heading = 'Page coverage — palace × domain (ECharts heatmap)';

    protected static ?int $sort = 4;

    protected string $height = '460px';

    protected function getOption(): array
    {
        $palaceKeys = array_merge(config('wiki.palaces'), ['unset']);
        // Domains 1..10 in fixed order, plus an "Unset" bucket keyed 0.
        $domainRows = config('wiki.domains') + [0 => 'Unset'];

        $counts = Page::query()
            ->where('is_meta', false)
            ->selectRaw('domain_id, palace, count(*) as c')
            ->groupBy('domain_id', 'palace')
            ->get();

        $matrix = [];
        foreach ($counts as $row) {
            $d = $row->domain_id ?? 0;
            $p = $row->palace ?: 'unset';
            $matrix[$d][$p] = (int) $row->c;
        }

        $palaceLabels = array_map(
            fn ($p) => $p === 'unset' ? 'Unset' : ucfirst(explode('-', $p)[0]),
            $palaceKeys,
        );
        $domainLabels = array_values($domainRows);
        $domainKeys = array_keys($domainRows);

        // ECharts heatmap wants [xIndex, yIndex, value] triples.
        $data = [];
        $max = 0;
        foreach ($palaceKeys as $xi => $p) {
            foreach ($domainKeys as $yi => $did) {
                $c = $matrix[$did][$p] ?? 0;
                $max = max($max, $c);
                $data[] = [$xi, $yi, $c];
            }
        }

        return [
            'tooltip' => ['position' => 'top'],
            'grid' => ['left' => 8, 'right' => 24, 'top' => 8, 'bottom' => 72, 'containLabel' => true],
            'xAxis' => [
                'type' => 'category',
                'data' => $palaceLabels,
                'splitArea' => ['show' => true],
                'axisLabel' => ['color' => '#9ca3af', 'rotate' => 30],
            ],
            'yAxis' => [
                'type' => 'category',
                'data' => $domainLabels,
                'splitArea' => ['show' => true],
                'axisLabel' => ['color' => '#9ca3af'],
            ],
            'visualMap' => [
                'min' => 0,
                'max' => max(1, $max),
                'calculable' => true,
                'orient' => 'horizontal',
                'left' => 'center',
                'bottom' => 8,
                'inRange' => ['color' => ['#fffbeb', '#fcd34d', '#b45309']],
                'textStyle' => ['color' => '#9ca3af'],
            ],
            'series' => [[
                'name' => 'Pages',
                'type' => 'heatmap',
                'data' => $data,
                'label' => ['show' => true, 'color' => '#334155', 'formatter' => '{@[2]}'],
                'emphasis' => ['itemStyle' => ['shadowBlur' => 8, 'shadowColor' => 'rgba(0,0,0,0.35)']],
            ]],
        ];
    }
}
