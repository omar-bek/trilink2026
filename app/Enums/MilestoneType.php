<?php

namespace App\Enums;

/**
 * Canonical category for a payment-schedule milestone.
 *
 * Why: payment_schedule and payments both store milestone as free text. The
 * old matching code did `str_contains(strtolower($p->milestone), $key)`,
 * which collides any time a user types a name containing another keyword
 * ("Final Deposit" matches both "deposit" and "final"). Routing through
 * this enum normalises both sides to the same canonical bucket so the
 * match is exact, not substring-based.
 *
 * Stored data isn't migrated — fromString() classifies legacy strings on
 * read, and new contracts can persist the raw enum value if desired.
 */
enum MilestoneType: string
{
    case ADVANCE    = 'advance';
    case PRODUCTION = 'production';
    case DELIVERY   = 'delivery';
    case FINAL      = 'final';
    case OTHER      = 'other';

    /**
     * Classify a free-text milestone label into a canonical type.
     * Order matters: more specific keywords (settlement → final) are
     * checked before broader ones (advance) so a label like
     * "Final Settlement" doesn't collapse to ADVANCE.
     */
    public static function fromString(?string $label): self
    {
        $key = strtolower(trim((string) $label));
        if ($key === '') {
            return self::OTHER;
        }

        // Check "final" / "settlement" FIRST — "Final Deposit" and
        // "Final Settlement" both contain "final", and we want the
        // FINAL bucket to win over the deposit/advance heuristic that
        // follows. Order matters here: most specific wins.
        return match (true) {
            str_contains($key, 'final') || str_contains($key, 'settlement')
                => self::FINAL,
            str_contains($key, 'deliver') || str_contains($key, 'shipment')
                => self::DELIVERY,
            str_contains($key, 'production')
                => self::PRODUCTION,
            $key === 'advance' || str_contains($key, 'advance') || str_contains($key, 'deposit')
                => self::ADVANCE,
            default => self::OTHER,
        };
    }

    public function translationKey(): string
    {
        return match ($this) {
            self::ADVANCE    => 'contracts.advance_payment',
            self::PRODUCTION => 'contracts.production_completion',
            self::DELIVERY   => 'contracts.delivery_payment',
            self::FINAL      => 'contracts.final_settlement',
            self::OTHER      => 'contracts.milestone',
        };
    }
}
