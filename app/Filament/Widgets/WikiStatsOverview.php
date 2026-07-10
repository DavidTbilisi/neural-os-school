<?php

namespace App\Filament\Widgets;

use App\Models\Page;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class WikiStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $pages = Page::count();
        $public = Page::where('visibility', Page::VISIBILITY_PUBLIC)->count();
        $private = Page::where('visibility', Page::VISIBILITY_PRIVATE)->count();
        $broken = DB::table('links')->where('resolved', false)->count();
        $orphans = Page::where('inbound_count', 0)->where('is_meta', false)->count();

        return [
            Stat::make('Pages', number_format($pages))
                ->description("{$public} public · {$private} private")
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),

            Stat::make('Published', number_format($public))
                ->description('live on the public site')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color($public > 0 ? 'success' : 'gray'),

            Stat::make('Links', number_format(DB::table('links')->count()))
                ->description(number_format($broken).' broken')
                ->descriptionIcon('heroicon-m-link')
                ->color($broken > 0 ? 'danger' : 'success'),

            Stat::make('Orphan pages', number_format($orphans))
                ->description('no inbound links')
                ->descriptionIcon('heroicon-m-arrow-down-on-square')
                ->color($orphans > 0 ? 'warning' : 'success'),

            Stat::make('Glossary terms', number_format(DB::table('glossary_terms')->count())),

            Stat::make('Unlocks', number_format(DB::table('unlocks')->count())),
        ];
    }
}
