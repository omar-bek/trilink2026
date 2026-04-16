<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\EscrowAccount;
use App\Models\EscrowRelease;
use App\Services\SidebarBadgeService;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic Eloquent observer that writes an `audit_logs` row whenever a
 * watched model is created / updated / deleted. Attached to the six core
 * transactional models (Contract, Bid, Rfq, PurchaseRequest, Payment,
 * Shipment) by AppServiceProvider::boot().
 *
 * Why a single shared observer instead of one per model?
 *
 *   - The audit row shape is identical: action + resource type + resource
 *     id + before/after diff. The model differences live in the dirty
 *     attributes, not the audit row.
 *   - Less code = fewer places to forget to wire something up later.
 *   - Phase 0 / task 0.11.
 *
 * Side benefit: every mutation also expires the sidebar badge cache for
 * the owning company so the next page load reflects fresh counts. The
 * forget cost is dominated by the DB write we just did, so it's free.
 */
class AuditLogObserver
{
    public function __construct(
        private readonly SidebarBadgeService $badges = new SidebarBadgeService,
    ) {}

    public function created(Model $model): void
    {
        $this->log(AuditAction::CREATE, $model, before: null, after: $model->getAttributes());
        $this->bustBadges($model);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();

        // Skip noise: if only `updated_at` changed (a touch() call),
        // there's nothing meaningful to record.
        unset($changes['updated_at']);
        if ($changes === []) {
            return;
        }

        $before = [];
        foreach ($changes as $key => $_) {
            $before[$key] = $model->getOriginal($key);
        }

        // Pick the most descriptive action: a status change is the most
        // common one and reads better in the UI than a generic "update".
        $action = isset($changes['status']) ? $this->statusToAction($changes['status']) : AuditAction::UPDATE;

        $this->log($action, $model, before: $before, after: $changes);
        $this->bustBadges($model);
    }

    public function deleted(Model $model): void
    {
        $this->log(AuditAction::DELETE, $model, before: $model->getOriginal(), after: null);
        $this->bustBadges($model);
    }

    /**
     * Map a status string to the closest AuditAction case so the audit log
     * reads as a verb rather than a generic "update".
     */
    private function statusToAction(string $status): AuditAction
    {
        return match ($status) {
            'approved' => AuditAction::APPROVE,
            'rejected' => AuditAction::REJECT,
            'submitted',
            'pending_approval' => AuditAction::SUBMIT,
            'signed', 'active' => AuditAction::SIGN,
            default => AuditAction::UPDATE,
        };
    }

    /**
     * @param  array<string,mixed>|null  $before
     * @param  array<string,mixed>|null  $after
     */
    private function log(AuditAction $action, Model $model, ?array $before, ?array $after): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'company_id' => $this->resolveCompanyId($model),
                'action' => $action->value,
                'resource_type' => class_basename($model),
                'resource_id' => $model->getKey(),
                'before' => $before ? $this->sanitize($before) : null,
                'after' => $after ? $this->sanitize($after) : null,
                'ip_address' => request()?->ip(),
                'user_agent' => substr((string) request()?->userAgent(), 0, 255),
                'status' => 'recorded',
            ]);
        } catch (\Throwable $e) {
            // Audit logging must never break the underlying business action.
            report($e);
        }
    }

    /**
     * Strip noisy / large fields before persisting the diff so audit_logs
     * stays a useful change log instead of a backup table.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function sanitize(array $payload): array
    {
        unset(
            $payload['updated_at'],
            $payload['created_at'],
            $payload['hash'],
            $payload['password'],
            $payload['remember_token'],
            $payload['two_factor_secret'],
            $payload['two_factor_recovery_codes'],
        );

        // Truncate very large string values so a single audit row can't
        // explode the table size — common with `description` columns.
        foreach ($payload as $k => $v) {
            if (is_string($v) && strlen($v) > 1000) {
                $payload[$k] = substr($v, 0, 1000).'… [truncated]';
            }
        }

        return $payload;
    }

    private function resolveCompanyId(Model $model): ?int
    {
        // Most of our transactional models have a company_id directly.
        // Contract uses buyer_company_id; we fall back to that.
        if (isset($model->company_id) && is_numeric($model->company_id)) {
            return (int) $model->company_id;
        }
        if (isset($model->buyer_company_id) && is_numeric($model->buyer_company_id)) {
            return (int) $model->buyer_company_id;
        }
        // EscrowAccount → contract → buyer company. EscrowRelease → account
        // → contract → buyer company. Walking the relation here keeps the
        // observer self-contained instead of leaking escrow knowledge into
        // every other transactional model.
        if ($model instanceof EscrowRelease && $model->escrow_account_id) {
            $accountCompanyId = EscrowAccount::query()
                ->whereKey($model->escrow_account_id)
                ->join('contracts', 'contracts.id', '=', 'escrow_accounts.contract_id')
                ->value('contracts.buyer_company_id');
            if ($accountCompanyId) {
                return (int) $accountCompanyId;
            }
        }
        if ($model instanceof EscrowAccount && $model->contract_id) {
            $contractCompanyId = Contract::query()
                ->whereKey($model->contract_id)
                ->value('buyer_company_id');
            if ($contractCompanyId) {
                return (int) $contractCompanyId;
            }
        }

        return auth()->user()?->company_id;
    }

    private function bustBadges(Model $model): void
    {
        $companyId = $this->resolveCompanyId($model);
        if ($companyId) {
            $this->badges->forgetForCompany($companyId);
        }
    }
}
