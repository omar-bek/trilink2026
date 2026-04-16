<?php

namespace App\Http\Controllers\Web\Contract;

use App\Enums\AmendmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesContract;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Http\Controllers\Web\Concerns\HandlesContractTerms;
use App\Models\ContractAmendment;
use App\Models\ContractAmendmentMessage;
use App\Models\ContractVersion;
use App\Models\User;
use App\Notifications\ContractAmendmentDecidedNotification;
use App\Notifications\ContractAmendmentMessageNotification;
use App\Notifications\ContractAmendmentProposedNotification;
use App\Services\ContractService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Bilateral amendment flow — propose, approve, reject, cancel, discuss.
 *
 * Extracted from ContractController so the pre-signature clause-negotiation
 * lifecycle lives in a single focused class. The parent class still hosts
 * show / index / signing / post-sign actions (terminate / decline / renew).
 *
 * Business rules enforced here (mirrored in the view):
 *   - window is DRAFT or PENDING_SIGNATURES with no party fully signed
 *   - a user cannot approve/reject an amendment from their own company
 *   - only the proposer (not their whole company) can cancel
 */
class AmendmentController extends Controller
{
    use AuthorizesContract;
    use FormatsForViews;
    use HandlesContractTerms;

    public function __construct(
        private readonly ContractService $service,
    ) {}

    /**
     * Propose a new amendment. Stored as PENDING_APPROVAL; the contract's
     * terms JSON is NOT touched until the counter-party approves. The
     * opposing company receives a notification with the proposer's name.
     */
    public function propose(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        if (! $this->canAmendNow($contract)) {
            return back()->withErrors(['amendment' => __('contracts.amendment_window_closed')]);
        }

        $validated = $request->validate([
            'kind' => ['required', 'in:modify,add'],
            'section_index' => ['required', 'integer', 'min:0'],
            'item_index' => ['nullable', 'integer', 'min:0'],
            'new_text' => ['required', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $sections = $this->parseTermsSections($contract->terms);
        $si = (int) $validated['section_index'];
        if (! isset($sections[$si])) {
            return back()->withErrors(['amendment' => __('contracts.amendment_section_missing')]);
        }

        $oldText = null;
        if ($validated['kind'] === 'modify') {
            $ii = (int) ($validated['item_index'] ?? -1);
            if ($ii < 0 || ! isset($sections[$si]['items'][$ii])) {
                return back()->withErrors(['amendment' => __('contracts.amendment_clause_missing')]);
            }
            $oldText = $sections[$si]['items'][$ii];
        }

        // Cheap language heuristic so we can warn the approver at merge
        // time that the amendment text will land in BOTH locales as-is —
        // we don't run it through a translator.
        $detectedLang = preg_match('/[\x{0600}-\x{06FF}]/u', $validated['new_text']) ? 'ar' : 'en';

        $amendment = ContractAmendment::create([
            'contract_id' => $contract->id,
            'from_version' => $contract->version,
            'changes' => [
                'kind' => $validated['kind'],
                'section_index' => $si,
                'section_title' => $sections[$si]['title'] ?? '',
                'item_index' => $validated['kind'] === 'modify' ? (int) $validated['item_index'] : null,
                'old_text' => $oldText,
                'new_text' => $validated['new_text'],
                'lang' => $detectedLang,
            ],
            'reason' => $validated['reason'] ?? null,
            'status' => AmendmentStatus::PENDING_APPROVAL,
            'requested_by' => $user->id,
            'approval_history' => [[
                'event' => 'proposed',
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'at' => now()->toIso8601String(),
            ]],
        ]);

        $this->notifyAmendment(
            $contract,
            $amendment,
            new ContractAmendmentProposedNotification($contract, $amendment, $this->displayName($user)),
            excludeCompanyId: $user->company_id,
        );

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.amendment_proposed'));
    }

    /**
     * Counter-party approves a pending amendment: merges the change into
     * the terms JSON (both locales for bilingual contracts, with lazy
     * upgrade for legacy single-locale), bumps the version, snapshots a
     * ContractVersion, and stamps approval_history.
     */
    public function approve(string $id, int $amendmentId): RedirectResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        if (! $this->canAmendNow($contract)) {
            return back()->withErrors(['amendment' => __('contracts.amendment_window_closed')]);
        }

        $amendment = ContractAmendment::where('contract_id', $contract->id)->findOrFail($amendmentId);

        if ($amendment->status !== AmendmentStatus::PENDING_APPROVAL) {
            return back()->withErrors(['amendment' => __('contracts.amendment_not_pending')]);
        }

        $proposer = User::find($amendment->requested_by);
        if ($proposer && $proposer->company_id === $user->company_id) {
            return back()->withErrors(['amendment' => __('contracts.amendment_self_approve_forbidden')]);
        }

        DB::transaction(function () use ($contract, $amendment, $user) {
            $changes = $amendment->changes ?? [];
            $si = (int) ($changes['section_index'] ?? -1);
            $ii = (int) ($changes['item_index'] ?? -1);
            $kind = $changes['kind'] ?? '';
            $newText = (string) ($changes['new_text'] ?? '');

            $decoded = is_string($contract->terms) ? json_decode($contract->terms, true) : $contract->terms;
            $isBilingual = is_array($decoded) && (isset($decoded['en']) || isset($decoded['ar']));

            $applyToSections = function (array $sections) use ($si, $ii, $kind, $newText) {
                if (! isset($sections[$si])) {
                    abort(422, __('contracts.amendment_section_missing'));
                }
                if ($kind === 'modify') {
                    if ($ii < 0 || ! isset($sections[$si]['items'][$ii])) {
                        abort(422, __('contracts.amendment_clause_missing'));
                    }
                    $sections[$si]['items'][$ii] = $newText;
                } else {
                    $sections[$si]['items'][] = $newText;
                }
                foreach ($sections as $idx => $sec) {
                    $sections[$idx]['items'] = array_values($sec['items'] ?? []);
                }

                return array_values($sections);
            };

            if ($isBilingual) {
                $newTerms = [
                    'en' => $applyToSections($this->parseTermsSections($decoded, 'en')),
                    'ar' => $applyToSections($this->parseTermsSections($decoded, 'ar')),
                ];
            } else {
                // Lazy upgrade of a legacy single-locale contract: the
                // amendment triggers a migration to the bilingual envelope
                // so that later PDF downloads in either language preserve
                // it. The "other" locale is regenerated from standard
                // clauses, then the amendment text lands in both sides
                // (same string — we don't auto-translate).
                $existing = $this->parseTermsSections($decoded);
                $regenerated = $this->service->regenerateTermsForLocale($contract, app()->getLocale() === 'ar' ? 'en' : 'ar');
                if (app()->getLocale() === 'ar') {
                    $newTerms = [
                        'en' => $applyToSections($regenerated),
                        'ar' => $applyToSections($existing),
                    ];
                } else {
                    $newTerms = [
                        'en' => $applyToSections($existing),
                        'ar' => $applyToSections($regenerated),
                    ];
                }
            }

            $contract->update([
                'terms' => json_encode($newTerms, JSON_UNESCAPED_UNICODE),
                'version' => $contract->version + 1,
            ]);

            $history = $amendment->approval_history ?? [];
            $history[] = [
                'event' => 'approved',
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'at' => now()->toIso8601String(),
            ];

            $amendment->update([
                'status' => AmendmentStatus::APPROVED,
                'approval_history' => $history,
            ]);

            ContractVersion::create([
                'contract_id' => $contract->id,
                'version' => $contract->version,
                'snapshot' => $contract->fresh()->toArray(),
                'created_by' => $user->id,
            ]);
        });

        $this->notifyAmendment(
            $contract,
            $amendment,
            new ContractAmendmentDecidedNotification($contract, $amendment, 'approved', $this->displayName($user)),
            excludeCompanyId: $user->company_id,
        );

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.amendment_approved'));
    }

    /**
     * Counter-party rejects a pending amendment. Terms stay untouched;
     * status flips to REJECTED with the event + reason appended.
     */
    public function reject(string $id, int $amendmentId, Request $request): RedirectResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        if (! $this->canAmendNow($contract)) {
            return back()->withErrors(['amendment' => __('contracts.amendment_window_closed')]);
        }

        $amendment = ContractAmendment::where('contract_id', $contract->id)->findOrFail($amendmentId);

        if ($amendment->status !== AmendmentStatus::PENDING_APPROVAL) {
            return back()->withErrors(['amendment' => __('contracts.amendment_not_pending')]);
        }

        $proposer = User::find($amendment->requested_by);
        if ($proposer && $proposer->company_id === $user->company_id) {
            return back()->withErrors(['amendment' => __('contracts.amendment_self_approve_forbidden')]);
        }

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $history = $amendment->approval_history ?? [];
        $history[] = [
            'event' => 'rejected',
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'reason' => $validated['rejection_reason'] ?? null,
            'at' => now()->toIso8601String(),
        ];

        $amendment->update([
            'status' => AmendmentStatus::REJECTED,
            'approval_history' => $history,
        ]);

        $this->notifyAmendment(
            $contract,
            $amendment,
            new ContractAmendmentDecidedNotification($contract, $amendment, 'rejected', $this->displayName($user)),
            excludeCompanyId: $user->company_id,
        );

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.amendment_rejected'));
    }

    /**
     * Only the original proposer (not their whole company) may cancel a
     * PENDING amendment — stricter than the approve/reject rule so team
     * members can't undo each other's work without an audit trail.
     */
    public function cancel(string $id, int $amendmentId): RedirectResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        $amendment = ContractAmendment::where('contract_id', $contract->id)->findOrFail($amendmentId);

        if ($amendment->status !== AmendmentStatus::PENDING_APPROVAL) {
            return back()->withErrors(['amendment' => __('contracts.amendment_not_pending')]);
        }

        if ((int) $amendment->requested_by !== (int) $user->id) {
            return back()->withErrors(['amendment' => __('contracts.amendment_cancel_forbidden')]);
        }

        $history = $amendment->approval_history ?? [];
        $history[] = [
            'event' => 'cancelled',
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'at' => now()->toIso8601String(),
        ];

        $amendment->update([
            'status' => AmendmentStatus::REJECTED,
            'approval_history' => $history,
        ]);

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.amendment_cancelled'));
    }

    /**
     * Append-only chat on an amendment thread. No edit/delete endpoint
     * by design — the thread is part of the legal audit trail.
     */
    public function postMessage(string $id, int $amendmentId, Request $request): RedirectResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        // Once the contract has moved out of the pre-signature window the
        // amendment thread is closed; a stale browser tab must not be
        // able to keep appending messages after the contract is signed.
        if (! $this->canAmendNow($contract)) {
            return back()->withErrors(['amendment' => __('contracts.amendment_window_closed')]);
        }

        $amendment = ContractAmendment::where('contract_id', $contract->id)->findOrFail($amendmentId);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = ContractAmendmentMessage::create([
            'contract_amendment_id' => $amendment->id,
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'body' => trim($validated['body']),
        ]);

        $this->notifyAmendment(
            $contract,
            $amendment,
            new ContractAmendmentMessageNotification($contract, $amendment, $message, $this->displayName($user)),
            excludeCompanyId: $user->company_id,
        );

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->withFragment('amendment-'.$amendment->id);
    }

    /**
     * JSON poll used by the blade thread component every ~10s. Two modes:
     *   - ?since=<iso>   → newer than timestamp, chronological
     *   - ?before=<id>   → up to 20 older than id, chronological
     */
    public function pollMessages(string $id, int $amendmentId, Request $request): JsonResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        $amendment = ContractAmendment::where('contract_id', $contract->id)->findOrFail($amendmentId);

        $since = $request->query('since');
        $before = $request->query('before');

        $query = ContractAmendmentMessage::where('contract_amendment_id', $amendment->id)
            ->with('user:id,first_name,last_name,email,company_id')
            ->orderBy('created_at');

        if ($before !== null && $before !== '') {
            $beforeId = (int) $before;
            $query->where('id', '<', $beforeId)
                ->orderBy('created_at', 'desc')
                ->limit(20);
            $messages = $query->get()->reverse()->values();
        } else {
            $sinceCarbon = null;
            if (is_string($since) && $since !== '') {
                try {
                    $sinceCarbon = Carbon::parse($since);
                } catch (\Throwable) {
                    $sinceCarbon = null;
                }
            }
            if ($sinceCarbon) {
                $query->where('created_at', '>', $sinceCarbon);
            }
            $messages = $query->get();
        }

        return response()->json([
            'amendment_id' => $amendment->id,
            'now' => now()->toIso8601String(),
            'messages' => $messages->map(function (ContractAmendmentMessage $m) use ($user) {
                $author = trim(($m->user?->first_name ?? '').' '.($m->user?->last_name ?? '')) ?: ($m->user?->email ?? '—');

                return [
                    'id' => $m->id,
                    'body' => $m->body,
                    'author' => $author,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'when' => $m->created_at?->diffForHumans() ?? '—',
                    'is_mine' => $user && (int) $m->company_id === (int) $user->company_id,
                ];
            })->all(),
        ]);
    }

    /**
     * Side-by-side track-changes view of two contract versions. Query
     * string: ?from=N (default: version-1), ?to=N (default: current).
     */
    public function versionsDiff(string $id, Request $request): View
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        $versions = ContractVersion::where('contract_id', $contract->id)
            ->orderBy('version')
            ->get();

        if ($versions->count() < 2) {
            return view('dashboard.contracts.versions-diff', [
                'contract' => $contract,
                'versions' => $versions,
                'fromVer' => null,
                'toVer' => null,
                'sectionsA' => [],
                'sectionsB' => [],
                'has_diff' => false,
            ]);
        }

        $maxVersion = (int) $versions->max('version');
        $defaultFrom = max(1, $maxVersion - 1);
        $fromVer = (int) $request->query('from', $defaultFrom);
        $toVer = (int) $request->query('to', $maxVersion);

        $a = $versions->firstWhere('version', $fromVer);
        $b = $versions->firstWhere('version', $toVer);

        $extractSections = function ($snapshot) {
            $terms = is_array($snapshot) ? ($snapshot['terms'] ?? null) : null;
            if (is_string($terms)) {
                $terms = json_decode($terms, true);
            }

            return $this->parseTermsSections($terms ?? []);
        };

        $sectionsA = $a ? $extractSections($a->snapshot) : [];
        $sectionsB = $b ? $extractSections($b->snapshot) : [];

        return view('dashboard.contracts.versions-diff', [
            'contract' => $contract,
            'versions' => $versions,
            'fromVer' => $fromVer,
            'toVer' => $toVer,
            'sectionsA' => $sectionsA,
            'sectionsB' => $sectionsB,
            'has_diff' => true,
        ]);
    }
}
