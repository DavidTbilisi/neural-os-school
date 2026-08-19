<?php

namespace App\Support;

use App\Models\Domain;

/**
 * Color as data.
 *
 * Ten pastel fill/text pairs live in tokens.css as `--color-domain-1…10`; this
 * class is the only place that decides which one a thing gets. Domains own a
 * hue by id, so a domain looks the same in the library, on a course card and in
 * a page header — the color is a second channel for "what is this", not
 * decoration a view picked.
 *
 * The class strings below are LITERAL on purpose: Tailwind scans this file
 * (see tailwind.config.js `content`), so it can only generate the utilities it
 * can actually see. Never build one by interpolation — `"bg-domain-{$n}"`
 * compiles to nothing.
 */
final class Palette
{
    /** How many hues the palette holds. */
    public const COUNT = 10;

    /** @var array<int, array{fill: string, text: string, border: string, dot: string}> */
    private const HUES = [
        1 => ['fill' => 'bg-domain-1', 'text' => 'text-domain-1-fg', 'border' => 'border-domain-1', 'dot' => 'bg-domain-1-fg'],
        2 => ['fill' => 'bg-domain-2', 'text' => 'text-domain-2-fg', 'border' => 'border-domain-2', 'dot' => 'bg-domain-2-fg'],
        3 => ['fill' => 'bg-domain-3', 'text' => 'text-domain-3-fg', 'border' => 'border-domain-3', 'dot' => 'bg-domain-3-fg'],
        4 => ['fill' => 'bg-domain-4', 'text' => 'text-domain-4-fg', 'border' => 'border-domain-4', 'dot' => 'bg-domain-4-fg'],
        5 => ['fill' => 'bg-domain-5', 'text' => 'text-domain-5-fg', 'border' => 'border-domain-5', 'dot' => 'bg-domain-5-fg'],
        6 => ['fill' => 'bg-domain-6', 'text' => 'text-domain-6-fg', 'border' => 'border-domain-6', 'dot' => 'bg-domain-6-fg'],
        7 => ['fill' => 'bg-domain-7', 'text' => 'text-domain-7-fg', 'border' => 'border-domain-7', 'dot' => 'bg-domain-7-fg'],
        8 => ['fill' => 'bg-domain-8', 'text' => 'text-domain-8-fg', 'border' => 'border-domain-8', 'dot' => 'bg-domain-8-fg'],
        9 => ['fill' => 'bg-domain-9', 'text' => 'text-domain-9-fg', 'border' => 'border-domain-9', 'dot' => 'bg-domain-9-fg'],
        10 => ['fill' => 'bg-domain-10', 'text' => 'text-domain-10-fg', 'border' => 'border-domain-10', 'dot' => 'bg-domain-10-fg'],
    ];

    /** Neutral fallback for anything with no domain (untagged pages, orphan gyms). */
    private const NEUTRAL = [
        'fill' => 'bg-surface-sunken', 'text' => 'text-muted',
        'border' => 'border-border', 'dot' => 'bg-fg-subtle',
    ];

    /**
     * The hue for a domain. Keyed by id so a rename never moves a color and a
     * new 11th domain wraps rather than blowing up.
     *
     * @return array{fill: string, text: string, border: string, dot: string}
     */
    public static function domain(Domain|int|null $domain): array
    {
        $id = $domain instanceof Domain ? $domain->id : $domain;

        if ($id === null) {
            return self::NEUTRAL;
        }

        return self::HUES[((int) $id - 1) % self::COUNT + 1] ?? self::NEUTRAL;
    }

    /** Just the chip classes for a domain — the most common call. */
    public static function chip(Domain|int|null $domain): string
    {
        $h = self::domain($domain);

        return $h['fill'].' '.$h['text'];
    }

    /**
     * The Knowledge Ladder ramp: a rung's hue encodes how far up it is, so a
     * filled ladder reads as a gradient rather than ten identical bars.
     *
     * @return array{fill: string, text: string, border: string, dot: string}
     */
    public static function rung(int $level): array
    {
        return self::nth($level);
    }

    /**
     * A hue by position, for ordered things with no domain of their own
     * (course modules, dashboard metrics). Stable for a given index, so a
     * module keeps its color between visits — position IS the identity here.
     *
     * @return array{fill: string, text: string, border: string, dot: string}
     */
    public static function nth(int $index): array
    {
        return self::HUES[abs($index) % self::COUNT + 1];
    }
}
