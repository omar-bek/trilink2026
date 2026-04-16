<?php

namespace App\Services\AI;

use App\Models\Contract;
use App\Models\Rfq;
use App\Models\User;

/**
 * Phase 5 / Sprint C — chat-style procurement assistant. The user types a
 * question ("which suppliers haven't shipped yet?") and the copilot
 * answers using a system prompt that knows about the platform's data
 * model.
 *
 * Live mode: Claude multi-turn chat. The system prompt is enriched with
 * lightweight context about the user's company so answers are tenant-
 * aware ("you have 4 contracts active, 2 with overdue shipments") instead
 * of generic advice.
 *
 * Mock mode: a small intent-matcher that responds to common questions
 * with deterministic data drawn from the DB. Always usable.
 */
class ProcurementCopilotService
{
    public function __construct(private readonly AnthropicClient $client) {}

    /**
     * Reply to the user's latest message. `$history` is an array of
     * `['role' => 'user'|'assistant', 'content' => '...']` ordered oldest
     * → newest. The current message must already be appended.
     *
     * Returns:
     *   ['source' => 'claude'|'mock', 'reply' => string]
     */
    public function respond(User $user, array $history): array
    {
        $latest = end($history);
        $latestText = is_array($latest) ? (string) ($latest['content'] ?? '') : '';

        $system = $this->buildSystemPrompt($user);

        if ($this->client->isConfigured() && ! empty($history)) {
            $reply = $this->client->chat($system, $history, maxTokens: 800);
            if ($reply) {
                return ['source' => 'claude', 'reply' => $reply];
            }
        }

        return [
            'source' => 'mock',
            'reply' => $this->mockReply($user, $latestText),
        ];
    }

    /**
     * System prompt enriched with lightweight tenant context. We
     * deliberately don't dump the full contract list — Claude doesn't
     * need to see every row, it just needs counts + summaries to ground
     * its answers.
     */
    private function buildSystemPrompt(User $user): string
    {
        $companyId = $user->company_id;

        $stats = [
            'open_rfqs' => Rfq::where('company_id', $companyId)->where('status', 'published')->count(),
            'active_contracts' => Contract::where('buyer_company_id', $companyId)->where('status', 'active')->count(),
            'pending_signature' => Contract::where('buyer_company_id', $companyId)->where('status', 'pending_signatures')->count(),
        ];

        return <<<TXT
You are TriLink Copilot, a procurement assistant for the TriLink B2B platform. You help buyers and procurement managers manage RFQs, bids, contracts, payments, and shipments.

User context:
- Company: {$user->company?->name}
- Role: {$user->role?->value}
- Open RFQs: {$stats['open_rfqs']}
- Active contracts: {$stats['active_contracts']}
- Contracts pending signature: {$stats['pending_signature']}

Be concise, practical, and tactical. Reference the user's actual numbers when relevant. If you don't know the answer, say so — never invent procurement data.

When the user asks for a list (e.g. "show me overdue payments"), respond with bullet points referencing TriLink screens like /dashboard/payments or /dashboard/contracts.
TXT;
    }

    /**
     * Mock intent matcher. Doesn't pretend to be smart — returns one of
     * a handful of canned answers based on keywords. Good enough to keep
     * the chat UI useful when no API key is configured.
     */
    private function mockReply(User $user, string $message): string
    {
        $lower = strtolower($message);
        $companyId = $user->company_id;

        if (str_contains($lower, 'rfq') || str_contains($lower, 'request')) {
            $count = Rfq::where('company_id', $companyId)->where('status', 'published')->count();

            return "You currently have {$count} open RFQ(s). View them at /dashboard/rfqs.";
        }

        if (str_contains($lower, 'contract') || str_contains($lower, 'sign')) {
            $pending = Contract::where('buyer_company_id', $companyId)->where('status', 'pending_signatures')->count();
            $active = Contract::where('buyer_company_id', $companyId)->where('status', 'active')->count();

            return "You have {$active} active contract(s) and {$pending} awaiting signature. Manage them at /dashboard/contracts.";
        }

        if (str_contains($lower, 'pay') || str_contains($lower, 'invoice')) {
            return 'Visit /dashboard/payments for the full payment list. Pending approvals appear at the top of the page.';
        }

        if (str_contains($lower, 'ship') || str_contains($lower, 'track')) {
            return 'Live shipment tracking is at /dashboard/shipments. Each shipment shows the carrier and last GPS update.';
        }

        if (str_contains($lower, 'supplier') || str_contains($lower, 'vendor')) {
            return 'Browse the supplier directory at /dashboard/suppliers/directory or the catalog at /dashboard/catalog.';
        }

        return "I can help with RFQs, bids, contracts, payments, shipments, and supplier discovery. Ask me something like 'how many contracts are pending signature?' or 'show me my open RFQs.'";
    }
}
