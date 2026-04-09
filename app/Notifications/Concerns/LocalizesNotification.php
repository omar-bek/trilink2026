<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\App;

/**
 * Shared helper for every notification class in the app. Ensures the
 * mail/database payload renders in the recipient's language regardless
 * of where the notification was dispatched (web request, queue worker,
 * cron, console command).
 *
 * Why this exists:
 *   - Laravel's HasLocalePreference auto-sets the locale before render
 *     for queueable notifications, but ONLY when the notifiable is sent
 *     via Notification::send($user, ...). Console commands and edge
 *     cases sometimes bypass that, and we also want one canonical place
 *     to build the greeting/footer used by every email so the look stays
 *     consistent across all 30+ notification types.
 *   - Putting trans() calls in every notification class becomes a sea
 *     of repetition. The `t()` helper here is a thin wrapper that
 *     resolves the locale once and remembers it for the rest of the
 *     render — including the database `toArray()` payload, which would
 *     otherwise pick up the *queue worker's* fallback locale.
 */
trait LocalizesNotification
{
    private ?string $resolvedLocale = null;

    /**
     * Resolve the locale to render this notification in for the given
     * notifiable. Falls back to app.locale when the recipient has no
     * preference (e.g. a non-User notifiable, or a guest action that
     * still got broadcast).
     */
    protected function localeFor(object $notifiable): string
    {
        if ($this->resolvedLocale !== null) {
            return $this->resolvedLocale;
        }

        if ($notifiable instanceof User) {
            $pref = $notifiable->preferredLocale();
            if ($pref) {
                return $this->resolvedLocale = $pref;
            }
        }

        return $this->resolvedLocale = App::getLocale() ?: config('app.locale', 'en');
    }

    /**
     * Translate a key in the notifiable's preferred locale. Always use
     * this instead of the global `__()` helper inside notification
     * classes — `__()` reads App::getLocale() which is the *worker's*
     * locale, not the recipient's.
     */
    protected function t(object $notifiable, string $key, array $replace = []): string
    {
        return trans($key, $replace, $this->localeFor($notifiable));
    }

    /**
     * Build the standard greeting line. Centralised so changing the
     * tone (e.g. from "Hi {name}" to "Dear {name}") only happens once.
     */
    protected function greetingFor(object $notifiable): string
    {
        $name = $notifiable->first_name ?? ($notifiable->name ?? null);
        if ($name) {
            return $this->t($notifiable, 'notifications.common.greeting_named', ['name' => $name]);
        }
        return $this->t($notifiable, 'notifications.common.greeting');
    }

    /**
     * Apply our standard mail subject + greeting + sign-off scaffolding
     * to a MailMessage. Each notification just appends its body lines
     * and an action button.
     */
    protected function baseMail(object $notifiable, string $subjectKey, array $subjectReplace = []): MailMessage
    {
        return (new MailMessage)
            ->subject($this->t($notifiable, $subjectKey, $subjectReplace))
            ->greeting($this->greetingFor($notifiable));
    }
}
