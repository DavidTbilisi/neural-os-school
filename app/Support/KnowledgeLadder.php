<?php

namespace App\Support;

use App\Models\Gym;

/**
 * The Red Queen Knowledge Ladder — the canonical 0–9 taxonomy of mastery that
 * every gym reads out. This is the single source of truth for the rungs; see
 * the wiki owner page `red-queen-knowledge-ladder` for the framework (note: the
 * wiki page's rung *names* differ slightly — this is the user's 0–9 revision,
 * which adds "Unknown 0" and tops out at "Strategic 9").
 *
 * The ladder is a DEPTH-of-mastery scale. A timed recognition/classification
 * gym can only certify the accuracy→reflex band, so `levelForGym()` maps a
 * session's accuracy + speed onto the rungs a gym legitimately measures and
 * caps at 7 (Reflexive). The deeper rungs — 2 Explainable, 8 Transferable,
 * 9 Strategic — require production / varied contexts, not a single timed drill,
 * so a recognition gym never awards them (they stay on the displayed ladder as
 * "what's beyond this gym").
 */
final class KnowledgeLadder
{
    /** @var array<int, array{name: string, standard: string}> level => rung */
    public const RUNGS = [
        0 => ['name' => 'Unknown', 'standard' => 'I cannot recognize it'],
        1 => ['name' => 'Familiar', 'standard' => 'I recognize it'],
        2 => ['name' => 'Explainable', 'standard' => 'I can explain it'],
        3 => ['name' => 'Retrievable', 'standard' => 'I can recall it'],
        4 => ['name' => 'Classifiable', 'standard' => 'I know when it applies'],
        5 => ['name' => 'Operational', 'standard' => 'I know what to do'],
        6 => ['name' => 'Executable', 'standard' => 'I can do it correctly'],
        7 => ['name' => 'Reflexive', 'standard' => 'I can do it fast'],
        8 => ['name' => 'Transferable', 'standard' => 'I can use it in new contexts'],
        9 => ['name' => 'Strategic', 'standard' => 'I can choose, adapt, teach, and debug it'],
    ];

    public const MIN = 0;

    public const MAX = 9;

    /** The rungs a timed recognition/classification gym can certify (in order). */
    public const GYM_RUNGS = [0, 1, 3, 4, 5, 7];

    /** The highest rung a recognition/classification gym awards. */
    public const GYM_CEILING = 7;

    /** @return array{level: int, name: string, standard: string} */
    public static function rung(int $level): array
    {
        $level = max(self::MIN, min(self::MAX, $level));

        return ['level' => $level, ...self::RUNGS[$level]];
    }

    /** @return array<int, array{level: int, name: string, standard: string}> all 10 rungs, low→high */
    public static function all(): array
    {
        return array_map(static fn (int $l) => self::rung($l), range(self::MIN, self::MAX));
    }

    /** The next rung up, or null at the ceiling. */
    public static function next(int $level, int $ceiling = self::GYM_CEILING): ?array
    {
        foreach (self::GYM_RUNGS as $r) {
            if ($r > $level && $r <= $ceiling) {
                return self::rung($r);
            }
        }

        return null;
    }

    /**
     * Map a completed session's performance onto a ladder rung for a
     * recognition/classification gym. Accuracy climbs the "knowing" rungs;
     * speed unlocks the reflex rung. Bands are relative to the gym's own
     * pass/promote/latency targets so tuning a gym re-tunes its ladder.
     */
    public static function levelForGym(Gym $gym, float $accuracy, ?int $medianLatencyMs): int
    {
        $pass = (float) ($gym->pass_accuracy ?? 0.80);
        $promote = (float) ($gym->promote_accuracy ?? 0.85);
        $latencyTarget = $gym->latency_target_ms;
        $fast = $latencyTarget !== null && $medianLatencyMs !== null && $medianLatencyMs <= $latencyTarget;

        // Below-chance / cannot recognize.
        if ($accuracy < 0.50) {
            return 0; // Unknown
        }
        // Recognizes, but not yet reliable.
        if ($accuracy < max(0.60, $pass - 0.10)) {
            return 1; // Familiar
        }
        // Recalls, approaching reliable classification.
        if ($accuracy < $pass) {
            return 3; // Retrievable
        }
        // Reliably knows when it applies (the classification competency).
        if ($accuracy < $promote) {
            return 4; // Classifiable
        }
        // Accurate and consistent, but not yet reflexive.
        if (! $fast) {
            return 5; // Operational
        }

        // Accurate AND fast — the gym's ceiling.
        return self::GYM_CEILING; // Reflexive
    }
}
