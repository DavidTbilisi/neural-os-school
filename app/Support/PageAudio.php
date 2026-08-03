<?php

namespace App\Support;

use App\Models\Page;

/**
 * Resolves the playable audio for a wiki page. Today that means the French
 * song units (pages staged under french-song/ by sync-content.sh); other page
 * families return [] and the partial renders nothing.
 *
 * Files live under public/audio/, staged by the same sync script. The page
 * slug carries a fr- namespace prefix that the audio trees don't (they predate
 * it and gyms/data/*.json references the plain names), so it is stripped for
 * the lookup. Two tracks, best first: the curated Suno take when David has
 * dropped one in (~/Music/french-song — see sync-content.sh), else just the
 * edge-tts study render that every unit ships with.
 */
class PageAudio
{
    /** @return list<array{label: string, url: string}> */
    public static function for(Page $page): array
    {
        if (! str_starts_with((string) $page->rel_path, 'french-song/')) {
            return [];
        }

        $slug = preg_replace('/^fr-/', '', $page->slug);
        $tracks = [];

        $candidates = [
            ["audio/french-song/{$slug}.mp3", 'The song'],
            ["audio/french-drill/{$slug}/00-full.mp3", 'Spoken reference — every French line, study pace (edge-tts)'],
        ];

        foreach ($candidates as [$path, $label]) {
            if (is_file(public_path($path))) {
                $tracks[] = ['label' => $label, 'url' => asset($path)];
            }
        }

        return $tracks;
    }
}
