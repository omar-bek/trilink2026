<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Buyer or supplier leaves a review on a completed contract. The rater's
 * company must be a party to the contract; they can only review the OTHER
 * side (not themselves). One review per contract per direction.
 */
class FeedbackController extends Controller
{
    public function store(string $contractId, Request $request): RedirectResponse
    {
        $contract = Contract::findOrFail((int) $contractId);
        $user = auth()->user();
        abort_unless($user?->hasPermission('contract.view'), 403);

        // Resolve rater + target companies.
        $raterCompanyId = $user->company_id;
        $partyIds = collect($contract->parties ?? [])->pluck('company_id')->filter()->all();
        $isBuyer = $raterCompanyId === $contract->buyer_company_id;
        $isParty = in_array($raterCompanyId, $partyIds, true);
        abort_unless($isBuyer || $isParty, 403, 'You must be a party of this contract to leave feedback.');

        // Target is whichever side the rater is NOT on.
        $targetCompanyId = $isBuyer
            ? collect($partyIds)->first(fn ($cid) => $cid !== $raterCompanyId)
            : $contract->buyer_company_id;

        abort_unless($targetCompanyId, 422, 'Cannot resolve review target.');

        $validated = $request->validate([
            'rating'              => ['required', 'integer', 'min:1', 'max:5'],
            'comment'             => ['nullable', 'string', 'max:2000'],
            'quality_score'       => ['nullable', 'integer', 'min:1', 'max:5'],
            'on_time_score'       => ['nullable', 'integer', 'min:1', 'max:5'],
            'communication_score' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        // updateOrCreate keeps the unique (contract_id, rater_company_id)
        // constraint from double-posting if the user mashes submit twice.
        Feedback::updateOrCreate(
            [
                'contract_id'      => $contract->id,
                'rater_company_id' => $raterCompanyId,
            ],
            array_merge($validated, [
                'target_company_id' => $targetCompanyId,
                'rater_user_id'     => $user->id,
            ])
        );

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('feedback.submitted') ?? 'Thank you for your feedback.');
    }
}
