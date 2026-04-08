<?php

namespace App\Support;

use App\Models\User;

/**
 * Reads a user's notification preferences (saved via Settings → Notifications)
 * and decides which channels are still allowed for a given preference key.
 *
 * Storage shape (in `users.custom_permissions['notifications']`):
 *   [
 *     'rfq_matches'          => true|false,
 *     'bid_updates'          => true|false,
 *     'contract_milestones'  => true|false,
 *     'messages'             => true|false,
 *     'marketing'            => true|false,
 *   ]
 *
 * Each notification class declares which preference key it falls under, then
 * calls `NotificationPreferences::channels($user, 'bid_updates', ['database', 'mail'])`
 * inside its `via()` method. The helper:
 *   - keeps `database` always (the in-app bell should never go silent — that's
 *     where users go to retroactively check anything they missed),
 *   - drops `mail` when the preference is OFF.
 *
 * Defaults: every preference defaults to ON except `marketing`, matching the
 * settings UI. New users with no `custom_permissions` row get the same defaults.
 */
class NotificationPreferences
{
    /**
     * Default value for each preference key when the user hasn't set one.
     */
    private const DEFAULTS = [
        'rfq_matches'         => true,
        'bid_updates'         => true,
        'contract_milestones' => true,
        'messages'            => true,
        'marketing'           => false,
    ];

    /**
     * Resolve the channel list a notification should actually use, given
     * the user's preferences.
     *
     * @param  User|null            $user
     * @param  string               $preferenceKey  one of the DEFAULTS keys
     * @param  array<int, string>   $channels       channels the notification would use by default
     * @return array<int, string>
     */
    public static function channels(?User $user, string $preferenceKey, array $channels): array
    {
        if (!$user) {
            return $channels;
        }

        if (self::wantsChannel($user, $preferenceKey)) {
            return $channels;
        }

        // Keep database (in-app), drop mail and any other delivery channels.
        return array_values(array_filter($channels, fn ($c) => $c === 'database'));
    }

    /**
     * Whether the user has opted into receiving the given preference key
     * outside the in-app bell. Returns the default when nothing is set.
     */
    public static function wantsChannel(User $user, string $preferenceKey): bool
    {
        $prefs = $user->custom_permissions['notifications'] ?? null;
        if (!is_array($prefs)) {
            return self::DEFAULTS[$preferenceKey] ?? true;
        }

        if (!array_key_exists($preferenceKey, $prefs)) {
            return self::DEFAULTS[$preferenceKey] ?? true;
        }

        return (bool) $prefs[$preferenceKey];
    }
}
