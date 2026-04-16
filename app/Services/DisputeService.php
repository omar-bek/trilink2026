<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Models\Dispute;
use App\Models\User;
use App\Notifications\DisputeNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;

class DisputeService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Dispute::query()
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($filters['contract_id'] ?? null, fn ($q, $v) => $q->where('contract_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
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
        ])->find($id);
    }

    public function create(array $data): Dispute
    {
        $data['sla_due_date'] = now()->addDays(7);
        $dispute = Dispute::create($data)->load(['contract', 'company']);

        $this->notifyParties($dispute, 'opened');

        return $dispute;
    }

    public function update(int $id, array $data): ?Dispute
    {
        $dispute = Dispute::findOrFail($id);

        $dispute->update($data);

        return $dispute->fresh(['contract', 'company']);
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

        $this->notifyParties($dispute->fresh(), 'escalated');

        return $dispute->fresh();
    }

    public function resolve(int $id, string $resolution): ?Dispute
    {
        $dispute = Dispute::findOrFail($id);

        $dispute->update([
            'status' => DisputeStatus::RESOLVED,
            'resolution' => $resolution,
            'resolved_at' => now(),
        ]);

        $this->notifyParties($dispute->fresh(), 'resolved');

        return $dispute->fresh();
    }

    /**
     * Send a DisputeNotification to both companies involved in a dispute.
     * The action ('opened'|'escalated'|'resolved') drives the notification's
     * type/title/color downstream in the formatter.
     */
    private function notifyParties(Dispute $dispute, string $action): void
    {
        $companyIds = collect([$dispute->company_id, $dispute->against_company_id])
            ->filter()
            ->unique()
            ->all();

        if (empty($companyIds)) {
            return;
        }

        $recipients = User::whereIn('company_id', $companyIds)->active()->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new DisputeNotification($dispute, $action));
        }
    }
}
