<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\BidStatus;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\NegotiationMessage;
use App\Models\User;
use App\Notifications\NegotiationCounterReceivedNotification;
use App\Notifications\NegotiationOfferAcceptedNotification;
use App\Notifications\NegotiationOfferRejectedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Structured negotiation flow on top of the negotiation_messages table.
 *
 * Governance rules (UAE B2B hardening — Phase A):
 *   - Every counter-offer carries a locked VAT snapshot (subtotal / VAT /
 *     total) computed from the ORIGINAL bid's tax_treatment +
 *     tax_rate_snapshot. Counter-offers cannot change the VAT treatment.
 *   - Every counter-offer has an expires_at (default: 2 UAE business days
 *     from posting, honouring weekends + federal public holidays). If the
 *     round has not been responded to by then, a sweeper flips it to
 *     rejected and the bid stays where it was.
 *   - Currency is locked to the bid's currency from the second round
 *     onwards. A counter posted in a different currency is rejected.
 *   - Round cap: either the bid's `negotiation_round_cap` column if set,
 *     or the default from config('negotiation.default_round_cap', 5).
 *   - Every state transition (counter / accept / reject / expire) writes
 *     an AuditLog row with before/after so the trail is tamper-evident.
 *   - Every transition also notifies the opposite side of the negotiation
 *     so nobody has to poll the UI.
 *   - Acceptance is a signed action: the acting user types their name,
 *     and we record signed_by_name + signed_at + signature_ip +
 *     signature_hash (sha256 of the agreed payload). This is the closest
 *     thing we have to wet-ink under UAE Electronic Transactions Law
 *     46/2021 without a full CA-issued e-sign certificate.
 */
class NegotiationService
{
    public const DEFAULT_ROUND_CAP = 5;

    public const DEFAULT_EXPIRY_BUSINESS_DAYS = 2;

    public function __construct(
        private readonly NegotiationVatCalculator $vat,
        private readonly SettlementCalendarService $calendar,
    ) {}

    /**
     * Post a free-text chat message in the negotiation room. Doesn't change
     * any state — pure conversation.
     */
    public function postText(Bid $bid, User $sender, string $body): NegotiationMessage
    {
        return NegotiationMessage::create([
            'bid_id' => $bid->id,
            'sender_id' => $sender->id,
            'sender_side' => $this->resolveSide($bid, $sender),
            'kind' => NegotiationMessage::KIND_TEXT,
            'body' => $body,
            'round_status' => NegotiationMessage::ROUND_OPEN,
        ]);
    }

    /**
     * Open a new round with a counter-offer. Enforces:
     *   - round cap (per bid)
     *   - currency lock (round >= 2 must match the bid's currency)
     *   - VAT recalculation (using the bid's tax_treatment)
     *   - expiry assignment (2 UAE business days by default)
     *
     * Previous open round (if any) is marked COUNTERED, so there is always
     * exactly one open round at a time per bid.
     *
     * @throws RuntimeException when the round cap has been reached.
     */
    public function openCounterOffer(Bid $bid, User $sender, array $offer, ?string $reason = null): NegotiationMessage
    {
        $currency = strtoupper((string) ($offer['currency'] ?? $bid->currency ?? 'AED'));
        $bidCurrency = strtoupper((string) ($bid->currency ?? 'AED'));

        $roundsSoFar = (int) NegotiationMessage::where('bid_id', $bid->id)
            ->where('kind', NegotiationMessage::KIND_COUNTER_OFFER)
            ->max('round_number');

        // Currency lock: the very first counter (round 1) MAY set the
        // currency (some RFQs don't have one pinned); from round 2
        // onwards it must match the bid's stored currency.
        if ($roundsSoFar >= 1 && $currency !== $bidCurrency) {
            throw new RuntimeException(__('negotiation.error_currency_locked', ['currency' => $bidCurrency]));
        }

        $cap = (int) ($bid->negotiation_round_cap ?? config('negotiation.default_round_cap', self::DEFAULT_ROUND_CAP));
        if ($roundsSoFar >= $cap) {
            throw new RuntimeException(__('negotiation.error_round_cap_reached', ['cap' => $cap]));
        }

        $amount = (float) ($offer['amount'] ?? 0);
        $vat = $this->vat->recalculate($bid, $amount);

        return DB::transaction(function () use ($bid, $sender, $offer, $reason, $currency, $roundsSoFar, $vat) {
            NegotiationMessage::where('bid_id', $bid->id)
                ->where('kind', NegotiationMessage::KIND_COUNTER_OFFER)
                ->where('round_status', NegotiationMessage::ROUND_OPEN)
                ->update([
                    'round_status' => NegotiationMessage::ROUND_COUNTERED,
                    'responded_at' => now(),
                    'responded_by' => $sender->id,
                ]);

            $nextRound = $roundsSoFar + 1;

            $payload = [
                'amount' => round((float) ($offer['amount'] ?? 0), 2),
                'currency' => $currency,
                'delivery_days' => isset($offer['delivery_days']) ? (int) $offer['delivery_days'] : null,
                'payment_terms' => $offer['payment_terms'] ?? null,
                'reason' => $reason,
                'tax_treatment' => $vat['treatment'],
                'tax_rate' => $vat['rate'],
                'subtotal_excl_tax' => $vat['subtotal_excl_tax'],
                'tax_amount' => $vat['tax_amount'],
                'total_incl_tax' => $vat['total_incl_tax'],
            ];

            $msg = NegotiationMessage::create([
                'bid_id' => $bid->id,
                'sender_id' => $sender->id,
                'sender_side' => $this->resolveSide($bid, $sender),
                'kind' => NegotiationMessage::KIND_COUNTER_OFFER,
                'body' => $reason,
                'offer' => $payload,
                'round_number' => $nextRound,
                'round_status' => NegotiationMessage::ROUND_OPEN,
                'expires_at' => $this->computeExpiry(now()),
                'subtotal_excl_tax' => $vat['subtotal_excl_tax'],
                'tax_amount' => $vat['tax_amount'],
                'total_incl_tax' => $vat['total_incl_tax'],
            ]);

            $this->audit($sender, $bid, AuditAction::SUBMIT, null, $msg->only([
                'id', 'round_number', 'round_status', 'offer', 'expires_at',
            ]));

            $this->notifyOpposite($bid, $sender, new NegotiationCounterReceivedNotification($bid->id, $msg->id));

            return $msg;
        });
    }

    /**
     * Accept the latest open round. Requires a typed signature so the
     * acceptance is recorded with wet-ink equivalent evidence (signer name,
     * IP, UA hash). Returns null when no round is open to accept.
     *
     * @param  array{name: string, ip?: ?string, user_agent?: ?string}  $signature
     */
    public function acceptOffer(Bid $bid, User $sender, array $signature): ?NegotiationMessage
    {
        $name = trim((string) ($signature['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException(__('negotiation.error_signature_required'));
        }

        return DB::transaction(function () use ($bid, $sender, $signature, $name) {
            $latest = $this->latestOpenRound($bid);
            if (! $latest) {
                return null;
            }

            if ($latest->isExpired()) {
                // Auto-expire before anyone tries to act on a stale round.
                $this->expireRound($latest);

                return null;
            }

            $before = $latest->only([
                'round_number', 'round_status', 'offer',
            ]);

            $hash = hash('sha256', json_encode([
                'bid_id' => $bid->id,
                'round' => $latest->round_number,
                'offer' => $latest->offer,
                'signer_id' => $sender->id,
                'signer_name' => $name,
                'at' => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE) ?: '');

            $latest->update([
                'round_status' => NegotiationMessage::ROUND_ACCEPTED,
                'responded_at' => now(),
                'responded_by' => $sender->id,
                'signed_by_name' => $name,
                'signed_at' => now(),
                'signature_ip' => $signature['ip'] ?? null,
                'signature_hash' => $hash,
            ]);

            // Apply the accepted offer back to the bid so the contract
            // pipeline picks up the negotiated values, not the original.
            $offer = $latest->offer ?? [];
            $updates = array_filter([
                'price' => $offer['amount'] ?? null,
                'currency' => $offer['currency'] ?? null,
                'delivery_time_days' => $offer['delivery_days'] ?? null,
                'payment_terms' => $offer['payment_terms'] ?? null,
                'subtotal_excl_tax' => $offer['subtotal_excl_tax'] ?? null,
                'tax_amount' => $offer['tax_amount'] ?? null,
                'total_incl_tax' => $offer['total_incl_tax'] ?? null,
                'status' => BidStatus::UNDER_REVIEW,
            ], fn ($v) => $v !== null);
            $bid->update($updates);

            $this->postText($bid, $sender, __('negotiation.system_accepted', ['n' => $latest->round_number, 'signer' => $name]));

            $this->audit($sender, $bid, AuditAction::APPROVE, $before, [
                'round_number' => $latest->round_number,
                'round_status' => NegotiationMessage::ROUND_ACCEPTED,
                'signer' => $name,
                'signature_hash' => $hash,
                'applied_to_bid' => $updates,
            ]);

            $this->notifyOpposite($bid, $sender, new NegotiationOfferAcceptedNotification($bid->id, $latest->id));

            return $latest->fresh();
        });
    }

    /**
     * Reject the latest open round without making a new counter.
     */
    public function rejectOffer(Bid $bid, User $sender, ?string $reason = null): ?NegotiationMessage
    {
        return DB::transaction(function () use ($bid, $sender, $reason) {
            $latest = $this->latestOpenRound($bid);
            if (! $latest) {
                return null;
            }

            $before = $latest->only(['round_number', 'round_status']);

            $latest->update([
                'round_status' => NegotiationMessage::ROUND_REJECTED,
                'responded_at' => now(),
                'responded_by' => $sender->id,
            ]);

            $this->postText($bid, $sender, __('negotiation.system_rejected', [
                'n' => $latest->round_number,
                'reason' => $reason ?: __('negotiation.no_reason'),
            ]));

            $this->audit($sender, $bid, AuditAction::REJECT, $before, [
                'round_number' => $latest->round_number,
                'round_status' => NegotiationMessage::ROUND_REJECTED,
                'reason' => $reason,
            ]);

            $this->notifyOpposite($bid, $sender, new NegotiationOfferRejectedNotification($bid->id, $latest->id));

            return $latest->fresh();
        });
    }

    /**
     * Cron-callable sweeper: mark every OPEN counter-offer whose
     * expires_at is in the past as REJECTED (auto-expired). Returns the
     * count of rounds that transitioned so the command can report.
     */
    public function expireStaleRounds(): int
    {
        $count = 0;
        NegotiationMessage::query()
            ->where('kind', NegotiationMessage::KIND_COUNTER_OFFER)
            ->where('round_status', NegotiationMessage::ROUND_OPEN)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->cursor()
            ->each(function (NegotiationMessage $msg) use (&$count) {
                $this->expireRound($msg);
                $count++;
            });

        return $count;
    }

    private function expireRound(NegotiationMessage $round): void
    {
        DB::transaction(function () use ($round) {
            $round->update([
                'round_status' => NegotiationMessage::ROUND_REJECTED,
                'expired_at' => now(),
                'responded_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => null,
                'company_id' => $round->bid?->company_id,
                'action' => AuditAction::REJECT,
                'resource_type' => 'negotiation_message',
                'resource_id' => $round->id,
                'before' => ['round_status' => NegotiationMessage::ROUND_OPEN],
                'after' => [
                    'round_status' => NegotiationMessage::ROUND_REJECTED,
                    'reason' => 'expired',
                ],
                'ip_address' => null,
                'user_agent' => 'system:negotiation-expiry-sweeper',
                'status' => 'success',
            ]);
        });
    }

    /**
     * Full negotiation timeline (text + offers) ordered by creation time.
     */
    public function timeline(Bid $bid)
    {
        return NegotiationMessage::with('sender')
            ->where('bid_id', $bid->id)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * The newest open counter-offer for a bid, if any.
     */
    public function latestOpenRound(Bid $bid): ?NegotiationMessage
    {
        return NegotiationMessage::where('bid_id', $bid->id)
            ->where('kind', NegotiationMessage::KIND_COUNTER_OFFER)
            ->where('round_status', NegotiationMessage::ROUND_OPEN)
            ->latest('round_number')
            ->first();
    }

    /**
     * Compute an expiry timestamp N UAE business days from `from`,
     * honouring weekends + federal public holidays.
     */
    public function computeExpiry(Carbon $from, ?int $businessDays = null): Carbon
    {
        $n = $businessDays ?? (int) config('negotiation.expiry_business_days', self::DEFAULT_EXPIRY_BUSINESS_DAYS);

        return $this->calendar->addBusinessDays($from->copy(), $n)->endOfDay();
    }

    /**
     * Determine which side of the negotiation a user is on.
     */
    public function resolveSide(Bid $bid, User $user): string
    {
        return $user->company_id === $bid->company_id ? 'supplier' : 'buyer';
    }

    /**
     * Notify every active user on the OPPOSITE side of the negotiation.
     * "Opposite" because the sender already knows what they just did —
     * it's the receiving side that needs the ping.
     */
    private function notifyOpposite(Bid $bid, User $sender, $notification): void
    {
        $senderSide = $this->resolveSide($bid, $sender);
        $targetCompanyId = $senderSide === 'buyer' ? $bid->company_id : $bid->rfq?->company_id;
        if (! $targetCompanyId) {
            return;
        }

        $recipients = User::query()
            ->where('company_id', $targetCompanyId)
            ->when(method_exists(User::class, 'scopeActive'), fn ($q) => $q->active())
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }

    private function audit(User $actor, Bid $bid, AuditAction $action, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'company_id' => $actor->company_id,
            'action' => $action,
            'resource_type' => 'bid',
            'resource_id' => $bid->id,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'status' => 'success',
        ]);
    }
}
