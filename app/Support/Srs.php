<?php

namespace App\Support;

use App\Models\GymItem;
use App\Models\SrsCard;
use Illuminate\Support\Carbon;

/**
 * The SRS scheduler — a plain Leitner ladder, deliberately inspectable
 * (no hidden ease factors): every exposure to a gym item, drill or review,
 * reschedules its card. Correct climbs one box (longer interval); a miss
 * drops back to box 1 and counts a lapse. A card is "due" once its interval
 * has elapsed; due cards feed review sessions and the per-module retention
 * read on the dashboard.
 *
 * Retention deliberately does NOT reopen the coverage gate — completion
 * stays sticky. This layer measures whether knowledge is *current*, and
 * says so, rather than silently revoking what was legitimately earned.
 */
final class Srs
{
    /** Leitner ladder: box → days until the next review. */
    public const INTERVALS = [1 => 1, 2 => 3, 3 => 7, 4 => 14, 5 => 30, 6 => 60];

    public const MAX_BOX = 6;

    /** Record one exposure and reschedule the card. $at overrides "now" for backfill replay. */
    public static function record(int $userId, GymItem $item, bool $correct, ?Carbon $at = null): SrsCard
    {
        $at = $at ?? Carbon::now();

        $card = SrsCard::firstOrNew(['user_id' => $userId, 'gym_item_id' => $item->id]);

        if (! $card->exists) {
            // First exposure enters box 1 either way — a new miss is not a lapse.
            $card->box = 1;
            $card->streak = $correct ? 1 : 0;
            $card->lapses = 0;
        } elseif ($correct) {
            $card->box = min($card->box + 1, self::MAX_BOX);
            $card->streak++;
        } else {
            $card->box = 1;
            $card->streak = 0;
            $card->lapses++;
        }

        $card->last_reviewed_at = $at;
        $card->due_at = $at->copy()->addDays(self::INTERVALS[$card->box]);
        $card->save();

        return $card;
    }
}
