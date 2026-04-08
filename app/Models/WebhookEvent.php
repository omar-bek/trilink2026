<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Replay-protection ledger for incoming webhook deliveries. Every webhook
 * handler (Stripe, PayPal, escrow bank partners) records the
 * provider-supplied event id BEFORE acting on the payload. The unique
 * (provider, event_id) index makes a replay attack a no-op: the second
 * insert throws and the handler returns 200 without re-running side
 * effects.
 *
 * Why a row instead of cache: webhook delivery is the source of truth for
 * money movements. Cache eviction would silently re-open a replay window;
 * a row in the database survives restarts and is auditable forever.
 */
class WebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Atomically claim a webhook for processing. Returns true if this is
     * the first delivery (proceed), false if it's a replay (skip).
     *
     * Uses an INSERT-with-unique-key to make the check race-free even
     * when two webhook deliveries arrive concurrently — the second insert
     * fails the unique constraint and the helper reports a replay.
     */
    public static function claim(string $provider, string $eventId, ?string $eventType = null, ?array $payload = null): bool
    {
        try {
            self::create([
                'provider'     => $provider,
                'event_id'     => $eventId,
                'event_type'   => $eventType,
                'payload'      => $payload,
                'processed_at' => now(),
            ]);
            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            // 23000 = integrity constraint violation (duplicate key) on
            // both MySQL and SQLite. Anything else is a real DB problem
            // we want to surface.
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'UNIQUE')) {
                return false;
            }
            throw $e;
        }
    }
}
