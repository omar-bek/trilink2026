<?php

namespace App\Services;

use App\Enums\AmendmentStatus;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractVersion;
use App\Models\User;
use App\Notifications\ContractSignedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ContractService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Contract::query()
            ->when($filters['buyer_company_id'] ?? null, fn ($q, $v) => $q->where('buyer_company_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('buyer_company_id', $v)
                    ->orWhereJsonContains('parties', ['company_id' => $v]);
            }))
            ->with('buyerCompany')
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Contract
    {
        return Contract::with(['buyerCompany', 'purchaseRequest', 'payments', 'shipments', 'disputes'])->find($id);
    }

    public function create(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            $contract = Contract::create($data);

            ContractVersion::create([
                'contract_id' => $contract->id,
                'version' => 1,
                'snapshot' => $contract->toArray(),
                'created_by' => auth()->id(),
            ]);

            return $contract->load('buyerCompany');
        });
    }

    public function update(int $id, array $data): ?Contract
    {
        $contract = Contract::find($id);
        if (!$contract) return null;

        $contract->update($data);
        return $contract->fresh('buyerCompany');
    }

    public function delete(int $id): bool
    {
        $contract = Contract::find($id);
        return $contract ? $contract->delete() : false;
    }

    public function sign(int $id, int $userId, int $companyId, ?string $signature = null): Contract|string
    {
        $contract = Contract::find($id);
        if (!$contract) return 'Contract not found';

        if (!in_array($contract->status, [ContractStatus::DRAFT, ContractStatus::PENDING_SIGNATURES])) {
            return 'Contract is not in a signable state';
        }

        $signatures = $contract->signatures ?? [];
        $alreadySigned = collect($signatures)->where('company_id', $companyId)->isNotEmpty();

        if ($alreadySigned) {
            return 'This party has already signed';
        }

        $signatures[] = [
            'user_id' => $userId,
            'company_id' => $companyId,
            'signature' => $signature,
            'signed_at' => now()->toISOString(),
        ];

        $contract->update([
            'signatures' => $signatures,
            'status' => ContractStatus::PENDING_SIGNATURES,
        ]);

        if ($contract->allPartiesHaveSigned()) {
            $contract->update(['status' => ContractStatus::ACTIVE]);
        }

        // Notify every user belonging to a party of the contract that a new
        // signature was applied. Each user sees who signed; the formatter
        // routes them to the contract page on click.
        $signer = User::find($userId);
        $signerName = $signer
            ? trim(($signer->first_name ?? '') . ' ' . ($signer->last_name ?? ''))
            : 'A party';

        $partyCompanyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();

        if (!empty($partyCompanyIds)) {
            $recipients = User::whereIn('company_id', $partyCompanyIds)->get();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ContractSignedNotification($contract, $signerName ?: 'A party'));
            }
        }

        return $contract->fresh('buyerCompany');
    }

    public function createAmendment(int $contractId, array $data): ContractAmendment
    {
        $contract = Contract::findOrFail($contractId);

        return ContractAmendment::create([
            'contract_id' => $contractId,
            'from_version' => $contract->version,
            'changes' => $data['changes'],
            'reason' => $data['reason'] ?? null,
            'status' => AmendmentStatus::DRAFT,
            'requested_by' => auth()->id(),
        ]);
    }

    public function getAmendments(int $contractId): LengthAwarePaginator
    {
        return ContractAmendment::where('contract_id', $contractId)
            ->with('requestedBy')
            ->latest()
            ->paginate(15);
    }

    public function approveAmendment(int $amendmentId): Contract
    {
        return DB::transaction(function () use ($amendmentId) {
            $amendment = ContractAmendment::findOrFail($amendmentId);
            $contract = $amendment->contract;

            $amendment->update(['status' => AmendmentStatus::APPROVED]);

            $contract->update(array_merge(
                $amendment->changes,
                ['version' => $contract->version + 1]
            ));

            ContractVersion::create([
                'contract_id' => $contract->id,
                'version' => $contract->version,
                'snapshot' => $contract->fresh()->toArray(),
                'created_by' => auth()->id(),
            ]);

            return $contract->fresh('buyerCompany');
        });
    }
}
