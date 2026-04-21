<?php

namespace App\Enums;

/**
 * Dispute lifecycle states. The flow is:
 *
 *   OPEN  →  ACKNOWLEDGED  →  UNDER_NEGOTIATION  →  IN_MEDIATION
 *         ↘                ↘                     ↘
 *          ESCALATED (government) ─────────────→  AWAITING_DECISION
 *                                                 ↓
 *                                       RESOLVED / WITHDRAWN / EXPIRED
 *
 * Any non-terminal state can transition to ESCALATED. RESOLVED,
 * WITHDRAWN and EXPIRED are terminal.
 */
enum DisputeStatus: string
{
    case OPEN = 'open';
    case ACKNOWLEDGED = 'acknowledged';
    case UNDER_NEGOTIATION = 'under_negotiation';
    case IN_MEDIATION = 'in_mediation';
    case AWAITING_DECISION = 'awaiting_decision';
    case ESCALATED = 'escalated';
    case RESOLVED = 'resolved';
    case WITHDRAWN = 'withdrawn';
    case EXPIRED = 'expired';
    // Legacy value kept so historical rows remain castable. New code
    // should treat UNDER_REVIEW as a synonym of UNDER_NEGOTIATION.
    case UNDER_REVIEW = 'under_review';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::RESOLVED, self::WITHDRAWN, self::EXPIRED => true,
            default => false,
        };
    }

    public function canAcknowledge(): bool
    {
        return $this === self::OPEN;
    }

    public function canMessage(): bool
    {
        return ! $this->isTerminal();
    }

    public function canOffer(): bool
    {
        return in_array($this, [
            self::OPEN,
            self::ACKNOWLEDGED,
            self::UNDER_NEGOTIATION,
            self::IN_MEDIATION,
            self::UNDER_REVIEW,
        ], true);
    }

    public function canEscalate(): bool
    {
        return ! $this->isTerminal() && $this !== self::ESCALATED;
    }

    public function canWithdraw(): bool
    {
        return ! $this->isTerminal();
    }
}
