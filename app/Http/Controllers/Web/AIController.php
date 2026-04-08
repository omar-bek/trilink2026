<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Contract;
use App\Services\AI\ContractRiskAnalysisService;
use App\Services\AI\DocumentOcrService;
use App\Services\AI\NegotiationAssistantService;
use App\Services\AI\PredictiveAnalyticsService;
use App\Services\AI\ProcurementCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Phase 5 — single controller for all AI-powered features. Each method
 * is a thin wrapper over the corresponding service in App\Services\AI.
 *
 * Authorization is per-method:
 *   - OCR + risk analysis: any user with the underlying entity permission
 *     (payment.view / contract.view) — the AI can't expose anything they
 *     can't already see.
 *   - Copilot: any authenticated user with ai.use.
 *   - Negotiation suggestions: bid.view + the user must be a party of
 *     the bid's RFQ company OR the bid company.
 */
class AIController extends Controller
{
    public function __construct(
        private readonly DocumentOcrService $ocr,
        private readonly NegotiationAssistantService $negotiation,
        private readonly ContractRiskAnalysisService $risk,
        private readonly PredictiveAnalyticsService $predictive,
        private readonly ProcurementCopilotService $copilot,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // OCR — upload a document and get structured fields back.
    // ─────────────────────────────────────────────────────────────────────

    public function ocrForm(): View
    {
        abort_unless(auth()->user()?->hasPermission('ai.use'), 403);
        return view('dashboard.ai.ocr');
    }

    public function ocrExtract(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('ai.use'), 403);

        $request->validate([
            'document'  => ['required', 'file', 'max:5120', 'mimes:pdf,png,jpg,jpeg,webp'],
            'hint_type' => ['nullable', 'string', 'in:invoice,bill_of_lading,packing_list'],
        ]);

        $path = $request->file('document')->store('ocr-uploads/' . auth()->id(), 'local');
        $result = $this->ocr->extract($path, 'local', $request->input('hint_type'));

        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Negotiation Assistant — counter-offer + talking points for an open bid.
    // ─────────────────────────────────────────────────────────────────────

    public function negotiationSuggestion(int $bid): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('ai.use') && $user?->hasPermission('bid.view'), 403);

        // The route parameter is named `{bid}` so Laravel passes the
        // numeric id positionally. We resolve to the model here so the
        // method signature stays primitive (avoids implicit binding).
        $bid = Bid::with(['rfq', 'company'])->findOrFail($bid);

        // Authorise — user's company must be a party of the negotiation.
        $isRfqOwner = $bid->rfq && $user->company_id === $bid->rfq->company_id;
        $isBidder   = $user->company_id === $bid->company_id;
        abort_unless($isRfqOwner || $isBidder, 403);

        $suggestion = $this->negotiation->suggestCounterOffer($bid);
        return response()->json($suggestion);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Contract Risk Analysis — flags + score for the contract.
    // ─────────────────────────────────────────────────────────────────────

    public function contractRisk(int $contract): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('ai.use') && $user?->hasPermission('contract.view'), 403);

        // Same pattern as negotiationSuggestion above — primitive id in,
        // model resolved here.
        $contract = Contract::findOrFail($contract);

        // Authorise — user's company must be a party of the contract.
        $partyIds = collect($contract->parties ?? [])->pluck('company_id')->push($contract->buyer_company_id)->filter()->all();
        abort_unless(in_array($user->company_id, $partyIds, true), 403);

        $analysis = $this->risk->analyse($contract);
        return response()->json($analysis);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Predictive Analytics — small JSON endpoint for the buyer dashboard.
    // ─────────────────────────────────────────────────────────────────────

    public function pricePrediction(Request $request): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('ai.use'), 403);

        $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'days'        => ['nullable', 'integer', 'min:7', 'max:730'],
        ]);

        $forecast = $this->predictive->averagePriceForCategory(
            (int) $request->input('category_id'),
            (int) $request->input('days', 180),
        );

        return response()->json($forecast ?? ['error' => 'not_enough_data']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Procurement Copilot — multi-turn chat persisted in the user's session.
    // ─────────────────────────────────────────────────────────────────────

    public function copilotPage(): View
    {
        abort_unless(auth()->user()?->hasPermission('ai.use'), 403);
        $history = session('copilot_history', []);
        return view('dashboard.ai.copilot', compact('history'));
    }

    public function copilotChat(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('ai.use'), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $history = session('copilot_history', []);
        $history[] = ['role' => 'user', 'content' => $data['message']];

        $result = $this->copilot->respond(auth()->user(), $history);
        $history[] = ['role' => 'assistant', 'content' => $result['reply']];

        // Cap conversation length so the session payload stays small.
        // The first prompt + last 18 messages is plenty for context.
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        session(['copilot_history' => $history]);

        return back();
    }

    public function copilotReset(): RedirectResponse
    {
        session()->forget('copilot_history');
        return back();
    }
}
