<?php

namespace App\Support;

use App\Models\User;

/**
 * The bar as data.
 *
 * Every link in the top bar is declared here once and rendered from here in
 * both shells — the public pastel layout and the dashboard nav — so a learner
 * who hides "Gyms" loses it everywhere, not on one page out of two.
 *
 * Two axes decide what someone actually sees:
 *
 *   audience   — who is ever offered an item at all (everyone / signed in /
 *                staff). A permission-shaped fact, never a preference.
 *   preference — the per-user order and hide list, stored on
 *                users.nav_preferences and applied on top of the audience set.
 *
 * Hides are stored as an opt-OUT list rather than a list of what to show. A
 * link added to ITEMS next year is therefore visible by default to everyone
 * who has already saved a preference, instead of silently missing for them.
 */
final class Navigation
{
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_AUTH = 'auth';

    public const AUDIENCE_STAFF = 'staff';

    /**
     * The canonical bar. Declaration order is the default order.
     *
     * @var array<string, array{label: string, audience: string, route?: string, url?: string, locked?: bool}>
     */
    private const ITEMS = [
        'library' => ['label' => 'Library', 'route' => 'library', 'audience' => self::AUDIENCE_ALL],
        'courses' => ['label' => 'Courses', 'route' => 'courses', 'audience' => self::AUDIENCE_ALL],
        'gyms' => ['label' => 'Gyms', 'route' => 'gyms', 'audience' => self::AUDIENCE_ALL],
        'my-courses' => ['label' => 'My courses', 'route' => 'courses.mine', 'audience' => self::AUDIENCE_AUTH],
        // Locked: the dashboard is the route back to the form that edits this
        // very bar. Movable, never hideable — otherwise a learner can customise
        // themselves into a bar with no way home.
        'dashboard' => ['label' => 'Dashboard', 'route' => 'dashboard', 'audience' => self::AUDIENCE_AUTH, 'locked' => true],
        'admin' => ['label' => 'Admin', 'url' => '/admin', 'audience' => self::AUDIENCE_STAFF],
    ];

    /**
     * The bar to render for this visitor: audience-filtered, in their order,
     * with their hidden items dropped. Guests get the defaults.
     *
     * @return list<array{key: string, label: string, href: string, route: ?string, locked: bool, hidden: bool}>
     */
    public static function for(?User $user): array
    {
        return array_values(array_filter(
            self::rowsFor($user),
            fn (array $row): bool => ! $row['hidden'],
        ));
    }

    /**
     * Every item this visitor is allowed to see, in their order, each flagged
     * hidden/locked. This is what the settings form edits — it must list the
     * switched-off items too, or they could never be switched back on.
     *
     * @return list<array{key: string, label: string, href: string, route: ?string, locked: bool, hidden: bool}>
     */
    public static function rowsFor(?User $user): array
    {
        $allowed = self::allowedKeys($user);
        $preferences = is_array($user?->nav_preferences) ? $user->nav_preferences : [];
        $hidden = array_flip(self::keys($preferences['hidden'] ?? [], $allowed));

        $rows = [];

        foreach (self::ordered($preferences['order'] ?? [], $allowed) as $key) {
            $item = self::ITEMS[$key];
            $locked = $item['locked'] ?? false;

            $rows[] = [
                'key' => $key,
                'label' => $item['label'],
                'href' => isset($item['route']) ? route($item['route']) : url($item['url']),
                'route' => $item['route'] ?? null,
                'locked' => $locked,
                'hidden' => ! $locked && isset($hidden[$key]),
            ];
        }

        return $rows;
    }

    /**
     * Persist one learner's arrangement. Both lists are scrubbed against what
     * that learner is allowed to see, so a stale or hand-posted key can neither
     * conjure a link nor hide a locked one.
     *
     * @param  list<string>  $order
     * @param  list<string>  $hidden
     */
    public static function save(User $user, array $order, array $hidden): void
    {
        $allowed = self::allowedKeys($user);

        $user->nav_preferences = [
            'order' => self::ordered($order, $allowed),
            'hidden' => array_values(array_diff(self::keys($hidden, $allowed), self::lockedKeys())),
        ];

        $user->save();
    }

    /** Forget the arrangement; the declaration order comes back. */
    public static function reset(User $user): void
    {
        $user->nav_preferences = null;
        $user->save();
    }

    /**
     * Saved keys first, in the order they were saved, then everything else in
     * declaration order — so a newly shipped link lands at the end of a
     * customised bar instead of vanishing from it.
     *
     * @param  mixed  $saved
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private static function ordered(mixed $saved, array $allowed): array
    {
        $saved = self::keys($saved, $allowed);

        return array_merge($saved, array_values(array_diff($allowed, $saved)));
    }

    /**
     * Whatever came in, reduced to a clean list of distinct allowed keys.
     *
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private static function keys(mixed $candidates, array $allowed): array
    {
        if (! is_array($candidates)) {
            return [];
        }

        $keys = array_filter($candidates, 'is_string');

        return array_values(array_unique(array_intersect($keys, $allowed)));
    }

    /** @return list<string> */
    private static function allowedKeys(?User $user): array
    {
        return array_keys(array_filter(self::ITEMS, fn (array $item): bool => match ($item['audience']) {
            self::AUDIENCE_AUTH => $user !== null,
            self::AUDIENCE_STAFF => (bool) $user?->isStaff(),
            default => true,
        }));
    }

    /** @return list<string> */
    private static function lockedKeys(): array
    {
        return array_keys(array_filter(self::ITEMS, fn (array $item): bool => $item['locked'] ?? false));
    }
}
