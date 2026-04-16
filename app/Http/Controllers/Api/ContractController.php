<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractVersion;
use App\Services\ContractService;
use App\Services\PkiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractService $service,
        private readonly PkiService $pkiService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'per_page']);
        $user = auth()->user();

        if (! $user->isAdmin() && ! $user->isGovernment()) {
            $filters['company_id'] = $user->company_id;
        }

        return $this->success($this->service->list($filters));
    }

    public function show(int $id): JsonResponse
    {
        $contract = $this->service->find($id);
        if (! $contract) {
            return $this->notFound();
        }

        // Authorization: a contract is visible to its parties only.
        // Parties = the buyer company AND every entry in the parties JSON
        // column. Government and admin roles can read all contracts.
        if (! $this->userIsContractParty($contract)) {
            return $this->notFound();
        }

        return $this->success($contract);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'purchase_request_id' => 'nullable|exists:purchase_requests,id',
            'buyer_company_id' => 'required|exists:companies,id',
            'parties' => 'required|array|min:1',
            'parties.*.company_id' => 'required|exists:companies,id',
            'parties.*.role' => 'required|string',
            'amounts' => 'nullable|array',
            'total_amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'payment_schedule' => 'nullable|array',
            'terms' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        return $this->created($this->service->create($data));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|string',
            'terms' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $contract = Contract::find($id);
        if (! $contract) {
            return $this->notFound();
        }
        if (! $this->userIsContractParty($contract)) {
            return $this->notFound();
        }

        $contract = $this->service->update($id, $data);

        return $contract ? $this->success($contract) : $this->notFound();
    }

    public function destroy(int $id): JsonResponse
    {
        $contract = Contract::find($id);
        if (! $contract) {
            return $this->notFound();
        }
        if (! $this->userIsContractParty($contract)) {
            return $this->notFound();
        }

        return $this->service->delete($id)
            ? $this->success(null, 'Contract deleted')
            : $this->notFound();
    }

    public function sign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'signature' => 'nullable|string',
            'private_key' => 'nullable|string',
        ]);

        $contract = Contract::find($id);
        if (! $contract) {
            return $this->notFound();
        }
        if (! $this->userIsContractParty($contract)) {
            return $this->notFound();
        }

        $contractHash = $this->pkiService->generateContractHash($contract->toArray());
        $digitalSignature = $this->pkiService->createDigitalSignature(
            auth()->id(),
            auth()->user()->company_id,
            $contractHash,
            $data['private_key'] ?? null
        );

        $result = $this->service->sign(
            $id,
            auth()->id(),
            auth()->user()->company_id,
            json_encode($digitalSignature)
        );

        if (is_string($result)) {
            return $this->error($result, 422);
        }

        return $this->success($result, 'Contract signed');
    }

    public function verifySignature(Request $request, int $id): JsonResponse
    {
        $contract = Contract::find($id);
        if (! $contract) {
            return $this->notFound();
        }
        if (! $this->userIsContractParty($contract)) {
            return $this->notFound();
        }

        $signatures = $contract->signatures ?? [];
        $verified = [];

        foreach ($signatures as $sig) {
            $sigData = is_string($sig) ? json_decode($sig, true) : $sig;
            $verified[] = [
                'company_id' => $sigData['company_id'] ?? null,
                'user_id' => $sigData['user_id'] ?? null,
                'signed_at' => $sigData['signed_at'] ?? null,
                'valid' => true, // In production, verify against public key
            ];
        }

        return $this->success([
            'contract_id' => $contract->id,
            'all_parties_signed' => $contract->allPartiesHaveSigned(),
            'signatures' => $verified,
        ]);
    }

    public function activate(int $id): JsonResponse
    {
        $contract = Contract::find($id);
        if (! $contract) {
            return $this->notFound();
        }
        if (! $this->userIsContractParty($contract)) {
            return $this->notFound();
        }

        if (! $contract->allPartiesHaveSigned()) {
            return $this->error('All parties must sign before activation', 422);
        }

        $contract->update(['status' => 'active']);

        return $this->success($contract->fresh(), 'Contract activated');
    }

    public function pdf(int $id): Response
    {
        $contract = Contract::with(['buyerCompany', 'purchaseRequest'])->find($id);
        if (! $contract) {
            return response()->json(['message' => 'Contract not found'], 404);
        }
        if (! $this->userIsContractParty($contract)) {
            return response()->json(['message' => 'Contract not found'], 404);
        }

        // Resolve the supplier party so the bilingual UAE PDF template can
        // render its full registration / TRN / address block. Mirrors the
        // Web\ContractController::pdf() lookup — keep them in sync.
        $supplierCompanyId = collect($contract->parties ?? [])
            ->firstWhere('role', 'supplier')['company_id'] ?? null;
        $supplierCompany = $supplierCompanyId ? Company::find($supplierCompanyId) : null;

        $pdf = Pdf::loadView('contracts.pdf', [
            'contract' => $contract,
            'buyerCompany' => $contract->buyerCompany,
            'supplierCompany' => $supplierCompany,
        ]);

        return $pdf->download("contract-{$contract->contract_number}.pdf");
    }

    /**
     * True iff the authenticated user belongs to a company that is a
     * party of the contract — i.e. the buyer company or any of the
     * company_ids in the parties JSON column. Admins and government
     * users always pass.
     */
    private function userIsContractParty(Contract $contract): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->isAdmin() || $user->isGovernment()) {
            return true;
        }

        $partyCompanyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();

        return in_array($user->company_id, $partyCompanyIds, true);
    }

    public function amendments(int $id): JsonResponse
    {
        return $this->success($this->service->getAmendments($id));
    }

    public function createAmendment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'changes' => 'required|array',
            'reason' => 'nullable|string',
        ]);

        $amendment = $this->service->createAmendment($id, $data);

        return $this->created($amendment);
    }

    public function showAmendment(int $contractId, int $amendmentId): JsonResponse
    {
        $amendment = ContractAmendment::where('contract_id', $contractId)
            ->with('requestedBy')
            ->find($amendmentId);

        return $amendment ? $this->success($amendment) : $this->notFound();
    }

    public function approveAmendment(int $contractId, int $amendmentId): JsonResponse
    {
        $contract = $this->service->approveAmendment($amendmentId);

        return $this->success($contract, 'Amendment approved');
    }

    public function versions(int $id): JsonResponse
    {
        $versions = ContractVersion::where('contract_id', $id)
            ->with('createdBy')
            ->orderByDesc('version')
            ->get();

        return $this->success($versions);
    }

    public function showVersion(int $contractId, int $version): JsonResponse
    {
        $contractVersion = ContractVersion::where('contract_id', $contractId)
            ->where('version', $version)
            ->with('createdBy')
            ->first();

        return $contractVersion ? $this->success($contractVersion) : $this->notFound();
    }

    public function compareVersions(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'version_a' => 'required|integer',
            'version_b' => 'required|integer',
        ]);

        $versionA = ContractVersion::where('contract_id', $id)
            ->where('version', $request->version_a)->first();
        $versionB = ContractVersion::where('contract_id', $id)
            ->where('version', $request->version_b)->first();

        if (! $versionA || ! $versionB) {
            return $this->notFound('One or both versions not found');
        }

        return $this->success([
            'version_a' => $versionA->snapshot,
            'version_b' => $versionB->snapshot,
        ]);
    }
}
