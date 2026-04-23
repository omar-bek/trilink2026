<?php

namespace App\Services;

use App\Enums\DisputeDecisionOutcome;
use App\Enums\DisputeOfferStatus;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Models\Dispute;
use App\Models\DisputeEvent;
use App\Models\DisputeMessage;
use App\Models\DisputeOffer;
use App\Models\User;
use App\Notifications\DisputeNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use RuntimeException;

class DisputeService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Dispute::query()
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('company_id', $v)->orWhere('against_company_id', $v);
            }))
            ->when($filters['contract_id'] ?? null, fn ($q, $v) => $q->where('contract_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['severity'] ?? null, fn ($q, $v) => $q->where('severity', $v))
            ->when($filters['raised_by'] ?? null, fn ($q, $v) => $q->where('raised_by', $v))
            ->when($filters['escalated'] ?? null, fn ($q) => $q->where('escalated_to_government', true))
            ->when($filters['assigned_to'] ?? null, fn ($q, $v) => $q->where('assigned_to', $v))
            ->with(['contract', 'company', 'raisedByUser', 'againstCompany'])
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Dispute
    {
        return Dispute::with([
            'contract', 'company', 'raisedByUser', 'againstCompany', 'assignedTo',
            'messages.user', 'messages.company',
            'offers.offeredByUser', 'offers.offeredByCompany', 'offers.respondedBy',
            'events.actorUser',
        ])->find($id);
    }

    /**
     * Create a new dispute and fire off the standard "opened" notifications.
     * Derives SLA from severity so `medium` → 5/30 days, `critical` → 2/10,
     * etc. A system message is appended so the conversation thread starts
     * with the full claim context.
     */
    public function create(array $data): Dispute
    {
        $severity = $data['severity'] instanceof DisputeSeverity
            ? $data['severity']
            : DisputeSeverity::tryFrom((string) ($data['severity'] ?? 'medium')) ?? DisputeSeverity::MEDIUM;

        $data['severity'] = $severity->value;
        $data['response_due_at'] = now()->addDays($severity->responseSlaDays());
        $data['sla_due_date'] = now()->addDays($severity->resolutionSlaDays());

        return DB::transaction(function () use ($data) {
            $dispute = Dispute::create($data)->load(['contract.escrowAccount', 'company', 'againstCompany']);

            // Phase B — the moment a dispute opens on a contract with an
            // active escrow, freeze the account so buyer can't drain it
            // mid-mediation. Unfreezes when the dispute resolves.
            $escrow = $dispute->contract?->escrowAccount;
            if ($escrow && $escrow->frozen_at === null) {
                app(EscrowService::class)->freeze($escrow, $dispute->id, 'dispute:'.$dispute->id);
            }

            $this->recordEvent($dispute, $data['raised_by'] ?? null, $dispute->company_id, 'opened', [
                'severity' => $dispute->severity?->value,
                'claim_amount' => $dispute->claim_amount,
                'remedy' => $dispute->requested_remedy?->value,
            ]);

            $this->systemMessage($dispute, __('disputes.system.opened', [
                'title' => $dispute->title,
                'type' => $dispute->type?->value ?? '—',
            ]));

            $this->notifyParties($dispute, 'opened');

            return $dispute;
        });
    }

    /**
     * Respondent acknowledges receipt. This is the "you got served" signal
     * that stops the response SLA clock and moves the dispute into active
     * negotiation. Only the respondent company may acknowledge.
     */
    public function acknowledge(Dispute $dispute, User $actor): Dispute
    {
        $this->assertRespondent($dispute, $actor);

        if ($dispute->status === DisputeStatus::OPEN || $dispute->status === null) {
            $dispute->update([
                'status' => DisputeStatus::ACKNOWLEDGED,
                'acknowledged_at' => now(),
            ]);

            $this->recordEvent($dispute, $actor->id, $actor->company_id, 'acknowledged');
            $this->systemMessage($dispute, __('disputes.system.acknowledged', [
                'company' => $dispute->againstCompany?->name ?? '—',
            ]));
            $this->notifyParties($dispute->fresh(), 'acknowledged');
        }

        return $dispute->fresh();
    }

    /**
     * Post a message into the conversation thread. Messages from parties
     * advance the state to UNDER_NEGOTIATION on the first exchange after
     * acknowledgement so the timeline correctly reflects active dialogue.
     */
    public function postMessage(Dispute $dispute, User $actor, string $body, bool $internal = false): DisputeMessage
    {
        if ($dispute->status?->isTerminal()) {
            throw new RuntimeException('Cannot message a closed dispute.');
        }

        $isParty = $dispute->isPartyCompany($actor->company_id);
        $isMediator = $dispute->assigned_to === $actor->id
            || in_array($actor->role?->value, ['government', 'admin'], true);

        if (! $isParty && ! $isMediator) {
            throw new RuntimeException('Only parties or the assigned mediator may post messages.');
        }

        $message = DisputeMessage::create([
            'dispute_id' => $dispute->id,
            'user_id' => $actor->id,
            'company_id' => $actor->company_id,
            'body' => $body,
            'is_internal' => $internal,
            'is_system' => false,
        ]);

        // First party message after acknowledgement kicks off negotiation.
        if (
            ! $internal
            && $dispute->status === DisputeStatus::ACKNOWLEDGED
            && $isParty
        ) {
            $dispute->update(['status' => DisputeStatus::UNDER_NEGOTIATION]);
        }

        $this->recordEvent($dispute, $actor->id, $actor->company_id, $internal ? 'message_internal' : 'message', [
            'message_id' => $message->id,
        ]);

        if (! $internal) {
            $this->notifyParties($dispute->fresh(), 'messaged');
        }

        return $message;
    }

    /**
     * Submit a structured settlement offer. An offer from either party
     * while status is OPEN/ACKNOWLEDGED moves the dispute into active
     * negotiation. A counter-offer links to its parent.
     */
    public function submitOffer(Dispute $dispute, User $actor, array $data): DisputeOffer
    {
        if (! $dispute->status?->canOffer()) {
            throw new RuntimeException('Dispute status does not allow settlement offers.');
        }
        $this->assertParty($dispute, $actor);

        $offer = DisputeOffer::create([
            'dispute_id' => $dispute->id,
            'parent_offer_id' => $data['parent_offer_id'] ?? null,
            'offered_by_user_id' => $actor->id,
            'offered_by_company_id' => $actor->company_id,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? $dispute->claim_currency ?? 'AED',
            'remedy' => $data['remedy'] ?? null,
            'terms' => $data['terms'],
            'status' => DisputeOfferStatus::PENDING->value,
            'expires_at' => $data['expires_at'] ?? now()->addDays(7),
        ]);

        if (in_array($dispute->status, [DisputeStatus::OPEN, DisputeStatus::ACKNOWLEDGED], true)) {
            $dispute->update(['status' => DisputeStatus::UNDER_NEGOTIATION]);
        }

        $this->recordEvent($dispute, $actor->id, $actor->company_id, 'offer_submitted', [
            'offer_id' => $offer->id,
            'amount' => (string) $offer->amount,
            'currency' => $offer->currency,
        ]);

        $this->systemMessage($dispute, __('disputes.system.offer_submitted', [
            'company' => $actor->company?->name ?? '—',
            'amount' => number_format((float) $offer->amount, 2).' '.$offer->currency,
        ]));

        $this->notifyParties($dispute->fresh(), 'offer');

        return $offer;
    }

    /**
     * Accept a pending offer from the other party. Acceptance is terminal
     * for the dispute — it transitions to RESOLVED with decision_outcome
     * = SETTLED, and the offer's amount becomes the awarded amount.
     */
    public function acceptOffer(DisputeOffer $offer, User $actor, ?string $note = null): Dispute
    {
        $this->assertOfferActionable($offer, $actor);

        return DB::transaction(function () use ($offer, $actor, $note) {
            $offer->update([
                'status' => DisputeOfferStatus::ACCEPTED->value,
                'responded_at' => now(),
                'responded_by' => $actor->id,
                'response_note' => $note,
            ]);

            $dispute = $offer->dispute;
            $dispute->update([
                'status' => DisputeStatus::RESOLVED,
                'decision_outcome' => DisputeDecisionOutcome::SETTLED->value,
                'decision_amount' => $offer->amount,
                'resolved_at' => now(),
                'resolution' => $note ?: $offer->terms,
            ]);

            $this->recordEvent($dispute, $actor->id, $actor->company_id, 'offer_accepted', [
                'offer_id' => $offer->id,
            ]);
            $this->systemMessage($dispute, __('disputes.system.offer_accepted', [
                'company' => $actor->company?->name ?? '—',
            ]));
            $this->notifyParties($dispute->fresh(), 'resolved');

            return $dispute->fresh();
        });
    }

    public function rejectOffer(DisputeOffer $offer, User $actor, ?string $note = null): DisputeOffer
    {
        $this->assertOfferActionable($offer, $actor);

        $offer->update([
            'status' => DisputeOfferStatus::REJECTED->value,
            'responded_at' => now(),
            'responded_by' => $actor->id,
            'response_note' => $note,
        ]);

        $this->recordEvent($offer->dispute, $actor->id, $actor->company_id, 'offer_rejected', [
            'offer_id' => $offer->id,
        ]);
        $this->systemMessage($offer->dispute, __('disputes.system.offer_rejected', [
            'company' => $actor->company?->name ?? '—',
        ]));
        $this->notifyParties($offer->dispute, 'offer');

        return $offer->fresh();
    }

    /**
     * File a counter-offer. The parent offer is marked COUNTERED and a new
     * PENDING offer is created in the counter-party's favour.
     */
    public function counterOffer(DisputeOffer $parent, User $actor, array $data): DisputeOffer
    {
        $this->assertOfferActionable($parent, $actor);

        return DB::transaction(function () use ($parent, $actor, $data) {
            $parent->update([
                'status' => DisputeOfferStatus::COUNTERED->value,
                'responded_at' => now(),
                'responded_by' => $actor->id,
                'response_note' => $data['note'] ?? null,
            ]);

            $this->recordEvent($parent->dispute, $actor->id, $actor->company_id, 'offer_countered', [
                'parent_offer_id' => $parent->id,
            ]);

            return $this->submitOffer($parent->dispute->fresh(), $actor, array_merge($data, [
                'parent_offer_id' => $parent->id,
            ]));
        });
    }

    /**
     * Claimant can withdraw their dispute at any non-terminal stage.
     */
    public function withdraw(Dispute $dispute, User $actor, ?string $reason = null): Dispute
    {
        if (! $dispute->status?->canWithdraw()) {
            throw new RuntimeException('Dispute cannot be withdrawn from its current state.');
        }
        if ($dispute->company_id !== $actor->company_id) {
            throw new RuntimeException('Only the claimant may withdraw a dispute.');
        }

        $dispute->update([
            'status' => DisputeStatus::WITHDRAWN,
            'withdrawn_at' => now(),
            'decision_outcome' => DisputeDecisionOutcome::WITHDRAWN->value,
            'resolution' => $reason,
            'resolved_at' => now(),
        ]);

        // Phase B — withdraw also unfreezes the escrow.
        $escrow = $dispute->contract?->escrowAccount;
        if ($escrow && $escrow->frozen_by_dispute_id === $dispute->id) {
            app(EscrowService::class)->unfreeze($escrow);
        }

        $this->recordEvent($dispute, $actor->id, $actor->company_id, 'withdrawn', ['reason' => $reason]);
        $this->systemMessage($dispute, __('disputes.system.withdrawn'));
        $this->notifyParties($dispute->fresh(), 'resolved');

        return $dispute->fresh();
    }

    /**
     * Mediator starts formal mediation — optional intermediate stage
     * before a decision.
     */
    public function startMediation(Dispute $dispute, User $mediator): Dispute
    {
        $dispute->update([
            'status' => DisputeStatus::IN_MEDIATION,
            'mediation_started_at' => now(),
            'assigned_to' => $mediator->id,
        ]);

        $this->recordEvent($dispute, $mediator->id, $mediator->company_id, 'mediation_started');
        $this->systemMessage($dispute, __('disputes.system.mediation_started', [
            'mediator' => trim(($mediator->first_name ?? '').' '.($mediator->last_name ?? '')),
        ]));
        $this->notifyParties($dispute->fresh(), 'mediation');

        return $dispute->fresh();
    }

    public function escalate(int $id): ?Dispute
    {
        $dispute = Dispute::findOrFail($id);
        if ($dispute->escalated_to_government) {
            return null;
        }

        $dispute->update([
            'escalated_to_government' => true,
            'status' => DisputeStatus::ESCALATED,
        ]);

        $this->recordEvent($dispute, auth()->id(), auth()->user()?->company_id, 'escalated');
        $this->systemMessage($dispute, __('disputes.system.escalated'));
        $this->notifyParties($dispute->fresh(), 'escalated');

        return $dispute->fresh();
    }

    /**
     * Formal adjudicated decision. Supersedes the old `resolve()` for
     * mediator/government actions: carries a structured outcome
     * (for_claimant / for_respondent / split) and an awarded amount.
     */
    public function decide(Dispute $dispute, User $actor, array $data): Dispute
    {
        $outcome = $data['decision_outcome'] instanceof DisputeDecisionOutcome
            ? $data['decision_outcome']
            : DisputeDecisionOutcome::from((string) $data['decision_outcome']);

        $dispute->update([
            'status' => DisputeStatus::RESOLVED,
            'decision_outcome' => $outcome->value,
            'decision_amount' => $data['decision_amount'] ?? null,
            'resolution' => $data['resolution'] ?? null,
            'resolved_at' => now(),
            'decided_by' => $actor->id,
        ]);

        // Phase B — the dispute is over; unfreeze the escrow account if we
        // froze it on open. The mediator's decision drives whether the
        // balance is released (supplier wins) or refunded (buyer wins)
        // — that's a separate call that the oversight UI issues.
        $escrow = $dispute->contract?->escrowAccount;
        if ($escrow && $escrow->frozen_by_dispute_id === $dispute->id) {
            app(EscrowService::class)->unfreeze($escrow);
        }

        $this->recordEvent($dispute, $actor->id, $actor->company_id, 'decided', [
            'outcome' => $outcome->value,
            'amount' => $data['decision_amount'] ?? null,
        ]);
        $this->systemMessage($dispute, __('disputes.system.decided', [
            'outcome' => __('disputes.outcome.'.$outcome->value),
        ]));
        $this->notifyParties($dispute->fresh(), 'resolved');

        return $dispute->fresh();
    }

    /**
     * Backwards-compatible free-text resolve. Now routes through decide()
     * with SETTLED as a sensible default outcome when no structured
     * decision is supplied — keeps existing callers working.
     */
    public function resolve(int $id, string $resolution): ?Dispute
    {
        $dispute = Dispute::findOrFail($id);

        return $this->decide($dispute, auth()->user(), [
            'decision_outcome' => DisputeDecisionOutcome::SETTLED,
            'resolution' => $resolution,
        ]);
    }

    public function update(int $id, array $data): ?Dispute
    {
        $dispute = Dispute::findOrFail($id);
        $dispute->update($data);

        return $dispute->fresh(['contract', 'company']);
    }

    // ──────────────────────────────────────────────────────────────
    // Internals
    // ──────────────────────────────────────────────────────────────

    public function recordEvent(Dispute $dispute, ?int $userId, ?int $companyId, string $event, array $metadata = []): DisputeEvent
    {
        return DisputeEvent::create([
            'dispute_id' => $dispute->id,
            'actor_user_id' => $userId,
            'actor_company_id' => $companyId,
            'event' => $event,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    private function systemMessage(Dispute $dispute, string $body): void
    {
        DisputeMessage::create([
            'dispute_id' => $dispute->id,
            'user_id' => null,
            'company_id' => null,
            'body' => $body,
            'is_internal' => false,
            'is_system' => true,
        ]);
    }

    private function assertParty(Dispute $dispute, User $user): void
    {
        if (! $dispute->isPartyCompany($user->company_id)) {
            throw new RuntimeException('Only party companies may perform this action.');
        }
    }

    private function assertRespondent(Dispute $dispute, User $user): void
    {
        if ($user->company_id !== $dispute->against_company_id) {
            throw new RuntimeException('Only the respondent company may acknowledge.');
        }
    }

    private function assertOfferActionable(DisputeOffer $offer, User $user): void
    {
        if (! $offer->status || $offer->status !== DisputeOfferStatus::PENDING) {
            throw new InvalidArgumentException('Only pending offers can be actioned.');
        }
        if ($offer->offered_by_company_id === $user->company_id) {
            throw new RuntimeException('You cannot action an offer you submitted.');
        }
        if (! $offer->dispute->isPartyCompany($user->company_id)) {
            throw new RuntimeException('Only the counter-party may respond to this offer.');
        }
        if ($offer->isExpired()) {
            throw new RuntimeException('This offer has expired.');
        }
    }

    private function notifyParties(Dispute $dispute, string $action): void
    {
        $companyIds = collect([$dispute->company_id, $dispute->against_company_id])
            ->filter()->unique()->all();

        if (empty($companyIds)) {
            return;
        }

        $recipients = User::whereIn('company_id', $companyIds)->active()->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new DisputeNotification($dispute, $action));
        }
    }
}
