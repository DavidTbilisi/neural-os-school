<?php

namespace App\Filament\Widgets;

use App\Models\Page;
use App\Models\ScoreLens;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class WikiStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $pages = Page::count();
        $public = Page::where('visibility', Page::VISIBILITY_PUBLIC)->count();
        $private = Page::where('visibility', Page::VISIBILITY_PRIVATE)->count();
        $broken = DB::table('links')->where('resolved', false)->count();
        $orphans = Page::where('inbound_count', 0)->where('is_meta', false)->count();
        $total = ScoreLens::total();

        $fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 1);

        return [
            Stat::make('Pages', number_format($pages))
                ->description("{$public} public · {$private} private")
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),

            Stat::make('Published', number_format($public))
                ->description('live on the public site')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color($public > 0 ? 'success' : 'gray'),

            Stat::make('Complexity', $fmt($total?->complexity))
                ->description('weighted knowledge mass')
                ->descriptionIcon('heroicon-m-cube-transparent')
                ->color('info'),

            Stat::make('Acquirement', $fmt($total?->acquirement))
                ->description('how much is absorbed')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),

            Stat::make('Absorbed', $total ? number_format($total->absorbed, 1).'%' : '—')
                ->description('acquirement ÷ complexity')
                ->descriptionIcon('heroicon-m-battery-50')
                ->color($total && $total->absorbed >= 20 ? 'success' : 'warning'),

            Stat::make('Links', number_format(DB::table('links')->count()))
                ->description(number_format($broken).' broken · '.number_format($orphans).' orphans')
                ->descriptionIcon('heroicon-m-link')
                ->color($broken > 0 ? 'danger' : 'success'),
        ];
    }
}
