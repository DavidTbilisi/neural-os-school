<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Base class for Filament dashboard widgets rendered with Apache ECharts.
 *
 * ECharts itself is loaded panel-wide by AdminPanelProvider's render hook
 * (the same Vite-built `resources/js/echarts.js` the learner front-end uses),
 * so subclasses only need to return an option array.
 *
 * Subclass and implement getOption():
 *
 *     class MyChart extends EChartWidget
 *     {
 *         protected static ?string $heading = 'My chart';
 *
 *         protected function getOption(): array
 *         {
 *             return [
 *                 'xAxis' => ['type' => 'category', 'data' => ['A', 'B', 'C']],
 *                 'yAxis' => ['type' => 'value'],
 *                 'series' => [['type' => 'bar', 'data' => [5, 12, 8]]],
 *             ];
 *         }
 *     }
 *
 * Use ChartWidget (Chart.js) for standard bar/line/pie; reach for this when you
 * need a chart type Chart.js can't do — heatmaps, treemaps, graphs, sankeys,
 * candlesticks, gauges — or heavy interactivity.
 */
abstract class EChartWidget extends Widget
{
    protected static string $view = 'filament.widgets.echart-widget';

    protected static ?string $heading = null;

    protected int|string|array $columnSpan = 'full';

    /** CSS height for the chart canvas. */
    protected string $height = '400px';

    /** The ECharts option object, as a PHP array. */
    abstract protected function getOption(): array;

    protected function getHeading(): ?string
    {
        return static::$heading;
    }

    protected function getViewData(): array
    {
        return [
            'option' => $this->getOption(),
            'heading' => $this->getHeading(),
            'height' => $this->height,
        ];
    }
}
