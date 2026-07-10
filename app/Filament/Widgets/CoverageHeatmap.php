<?php

namespace App\Filament\Widgets;

use App\Models\Page;
use Filament\Widgets\Widget;

class CoverageHeatmap extends Widget
{
    protected static string $view = 'filament.widgets.coverage-heatmap';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $palaces = config('wiki.palaces');
        $palaceKeys = array_merge($palaces, ['unset']);
        // domain rows: 1..10 in order, then an "Unset" bucket (id 0)
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

        $max = 0;
        $rowTotal = [];
        $colTotal = array_fill_keys($palaceKeys, 0);
        $grand = 0;
        foreach ($domainRows as $did => $name) {
            $rt = 0;
            foreach ($palaceKeys as $p) {
                $c = $matrix[$did][$p] ?? 0;
                $rt += $c;
                $colTotal[$p] += $c;
                $max = max($max, $c);
            }
            $rowTotal[$did] = $rt;
            $grand += $rt;
        }

        $palaceLabels = [];
        foreach ($palaceKeys as $p) {
            $palaceLabels[$p] = $p === 'unset' ? 'Unset' : ucfirst(explode('-', $p)[0]);
        }

        return compact('domainRows', 'palaceKeys', 'palaceLabels', 'matrix', 'max', 'rowTotal', 'colTotal', 'grand');
    }
}
