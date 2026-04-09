<?php

namespace App\Support;

use App\Models\User;

/**
 * Reads a user's notification preferences and decides which channels are
 * still allowed for a given preference key.
 *
 * The platform has TWO ways a user can express their preferences and
 * this helper bridges them so notifications respect either:
 *
 *   1. The Profile page form (Sprint D.17) writes to the
 *      `users.notification_preferences` JSON column. It carries
 *      *channel-level* toggles (database / mail) and a digest mode
 *      (realtime / daily / off). When this column is set, it wins.
 *
 *   2. The legacy Settings → Notifications form writes to
 *      `users.custom_permissions['notifications']` as a flat map of
 *      *category toggles* (rfq_matches, bid_updates, …). When the
 *      Profile column is empty we fall back to this — that way users
 *      who set their preferences in the old UI before the migration
 *      keep getting what they asked for.
 *
 * Each notification class declares which preference key it falls under
 * by calling `NotificationPreferences::channels($user, 'bid_updates',
 * ['database', 'mail'])` inside its `via()` method. The helper:
 *   - keeps `database` always (the in-app bell should never go silent —
 *     that's where users go to retroactively check anything they missed),
 *   - drops `mail` when EITHER preference layer says so.
 *
 * Defaults: every preference defaults to ON except `marketing`, matching
 * the settings UI. New users with no row anywhere get the same defaults.
 */
class NotificationPreferences
{
    /**
     * Default value for each preference category when the user hasn't
     * set one. Keep this list in sync with the categories the Settings
     * form actually exposes — adding a new key here without a UI toggle
     * means the default rules forever.
     */
    public const DEFAULTS = [
        'rfq_matches'         => true,
        'bid_updates'         => true,
        'contract_milestones' => true,
        'payment_updates'     => true,
        'compliance_alerts'   => true,
        'privacy_updates'     => true,
        'messages'            => true,
        'system_updates'      => true,
        'marketing'           => false,
    ];

    /**
     * Resolve the channel list a notification should actually use,
     * given the user's preferences across BOTH storage layers.
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

        // Layer 1: the new Profile-form JSON column wins when present.
        // It controls channels at the global level — if the user has
        // disabled mail entirely, we drop mail from every category.
        $jsonPrefs = is_array($user->notification_preferences) ? $user->notification_preferences : null;
        if ($jsonPrefs !== null) {
            $globalChannels = $jsonPrefs['channels'] ?? null;
            $digestMode     = $jsonPrefs['digest']['mode'] ?? 'realtime';

            if (is_array($globalChannels)) {
                $channels = array_values(array_filter(
                    $channels,
                    fn ($c) => $c === 'database' || (bool) ($globalChannels[$c] ?? true)
                ));
            }

            // Per-type overrides take precedence over the global toggle.
            $perType = $jsonPrefs['types'][$preferenceKey] ?? null;
            if (is_array($perType)) {
                $channels = array_values(array_filter(
                    $channels,
                    fn ($c) => $c === 'database' || in_array($c, $perType, true)
                ));
            }

            // "Off" digest mode silences email entirely — bell stays
            // on so the user can still find what they missed.
            if ($digestMode === 'off') {
                $channels = array_values(array_filter($channels, fn ($c) => $c === 'database'));
            }
        }

        // Layer 2: the legacy category toggle. Only consulted when the
        // user hasn't opted into a per-type override on the new form.
        if (!self::wantsCategory($user, $preferenceKey)) {
            $channels = array_values(array_filter($channels, fn ($c) => $c === 'database'));
        }

        return array_values(array_unique($channels));
    }

    /**
     * Whether the user has opted into receiving the given category
     * outside the in-app bell. Returns the default when nothing is set.
     */
    public static function wantsCategory(User $user, string $preferenceKey): bool
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

    /**
     * @deprecated Kept for binary-compatibility with code calling the
     *             old name. Use wantsCategory() in new code.
     */
    public static function wantsChannel(User $user, string $preferenceKey): bool
    {
        return self::wantsCategory($user, $preferenceKey);
    }
}
