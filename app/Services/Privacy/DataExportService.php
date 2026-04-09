<?php

namespace App\Services\Privacy;

use App\Models\Consent;
use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Builds a portable archive of every piece of personal data the platform
 * holds about a given user. Used by the DSAR endpoint (Article 13 of
 * Federal Decree-Law 45/2021 — Right of Access).
 *
 * The output is a single ZIP file containing:
 *
 *   /index.json              — manifest + summary stats
 *   /profile.json            — the user's own row + role + permissions
 *   /company.json            — the user's company snapshot (if any)
 *   /consents.json           — full consent ledger
 *   /privacy_requests.json   — every prior privacy request
 *   /audit_logs.json         — audit log entries authored by this user
 *   /purchase_requests.json  — PRs they created
 *   /bids.json               — bids they submitted
 *   /payments.json           — payments they triggered
 *   /disputes.json           — disputes they filed
 *
 * Each JSON file is pretty-printed (PDPL Article 13(2) — "in a structured,
 * commonly used and machine-readable format" — JSON satisfies this and is
 * also human-readable for the auditor).
 *
 * Idempotent: rebuilds the archive on every call. The privacy_requests
 * row is what tracks fulfilment state — this service is the worker.
 */
class DataExportService
{
    public function __construct(
        private readonly ConsentLedger $consents,
    ) {
    }

    /**
     * Build the archive and persist it on the local disk under
     * privacy-exports/{user_id}/{request_id}.zip. Returns the storage
     * path so the caller can stamp it into the PrivacyRequest's
     * fulfillment_metadata.
     */
    public function buildArchive(User $user, ?PrivacyRequest $request = null): string
    {
        $user->loadMissing(['company', 'branch']);

        $payload = [
            'index'             => $this->indexPayload($user, $request),
            'profile'           => $this->profilePayload($user),
            'company'           => $this->companyPayload($user),
            'consents'          => $this->consentsPayload($user),
            'privacy_requests'  => $this->privacyRequestsPayload($user),
            'audit_logs'        => $this->auditLogsPayload($user),
            'purchase_requests' => $this->prPayload($user),
            'bids'              => $this->bidsPayload($user),
            'payments'          => $this->paymentsPayload($user),
            'disputes'          => $this->disputesPayload($user),
        ];

        $zipPath = sprintf(
            'privacy-exports/%d/%s.zip',
            $user->id,
            $request?->id ?? ('adhoc-' . now()->format('Ymd-His'))
        );

        $absolute = Storage::disk('local')->path($zipPath);
        $dir = dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create archive at {$absolute}");
        }

        foreach ($payload as $name => $section) {
            $zip->addFromString(
                "{$name}.json",
                json_encode($section, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        $zip->close();

        return $zipPath;
    }

    private function indexPayload(User $user, ?PrivacyRequest $request): array
    {
        return [
            'subject_user_id'   => $user->id,
            'generated_at'      => now()->toIso8601String(),
            'request_id'        => $request?->id,
            'data_residency'    => config('data_residency.region'),
            'controller'        => config('app.name'),
            'pdpl_basis'        => 'Federal Decree-Law 45/2021, Article 13 — Right of Access',
            'sections_included' => [
                'profile', 'company', 'consents', 'privacy_requests',
                'audit_logs', 'purchase_requests', 'bids', 'payments', 'disputes',
            ],
            'note'              => 'This archive is your personal data as recorded by the platform. Consent and audit log entries are append-only and cannot be deleted, only superseded.',
        ];
    }

    private function profilePayload(User $user): array
    {
        return [
            'id'         => $user->id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'phone'      => $user->phone ?? null,
            'role'       => $user->role?->value ?? null,
            'status'     => $user->status?->value ?? null,
            'company_id' => $user->company_id,
            'branch_id'  => $user->branch_id,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    private function companyPayload(User $user): ?array
    {
        $c = $user->company;
        if (!$c) {
            return null;
        }
        return [
            'id'                  => $c->id,
            'name'                => $c->name,
            'name_ar'             => $c->name_ar ?? null,
            'registration_number' => $c->registration_number,
            'tax_number'          => $c->tax_number, // decrypted by the cast on read
            'type'                => $c->type?->value,
            'status'              => $c->status?->value,
            'email'               => $c->email,
            'address'             => $c->address ?? null,
            'city'                => $c->city ?? null,
            'country'             => $c->country ?? null,
            'created_at'          => $c->created_at?->toIso8601String(),
        ];
    }

    private function consentsPayload(User $user): array
    {
        return $this->consents->ledgerFor($user)
            ->map(fn (Consent $c) => [
                'id'           => $c->id,
                'consent_type' => $c->consent_type,
                'version'      => $c->version,
                'granted_at'   => $c->granted_at?->toIso8601String(),
                'withdrawn_at' => $c->withdrawn_at?->toIso8601String(),
                'ip_address'   => $c->ip_address,
                'user_agent'   => $c->user_agent,
            ])
            ->all();
    }

    private function privacyRequestsPayload(User $user): array
    {
        return PrivacyRequest::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PrivacyRequest $r) => [
                'id'              => $r->id,
                'request_type'    => $r->request_type,
                'status'          => $r->status,
                'requested_at'    => $r->requested_at?->toIso8601String(),
                'scheduled_for'   => $r->scheduled_for?->toIso8601String(),
                'completed_at'    => $r->completed_at?->toIso8601String(),
                'rejection_reason'=> $r->rejection_reason,
            ])
            ->all();
    }

    private function auditLogsPayload(User $user): array
    {
        return $user->auditLogs()
            ->orderByDesc('id')
            ->limit(5000)
            ->get(['id', 'action', 'resource_type', 'resource_id', 'ip_address', 'created_at'])
            ->map(fn ($a) => [
                'id'            => $a->id,
                'action'        => $a->action,
                'resource_type' => $a->resource_type,
                'resource_id'   => $a->resource_id,
                'ip_address'    => $a->ip_address,
                'at'            => $a->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function prPayload(User $user): array
    {
        return $user->purchaseRequests()
            ->get(['id', 'pr_number', 'title', 'status', 'total_budget', 'currency', 'created_at'])
            ->map(fn ($p) => [
                'id'           => $p->id,
                'pr_number'    => $p->pr_number ?? null,
                'title'        => $p->title,
                'status'       => $p->status?->value ?? $p->status,
                'total_budget' => $p->total_budget,
                'currency'     => $p->currency,
                'created_at'   => $p->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function bidsPayload(User $user): array
    {
        return $user->bids()
            ->get(['id', 'rfq_id', 'status', 'price', 'currency', 'created_at'])
            ->map(fn ($b) => [
                'id'         => $b->id,
                'rfq_id'     => $b->rfq_id,
                'status'     => $b->status?->value ?? $b->status,
                'price'      => $b->price,
                'currency'   => $b->currency,
                'created_at' => $b->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function paymentsPayload(User $user): array
    {
        return $user->payments()
            ->get(['id', 'contract_id', 'status', 'amount', 'currency', 'created_at'])
            ->map(fn ($p) => [
                'id'          => $p->id,
                'contract_id' => $p->contract_id,
                'status'      => $p->status?->value ?? $p->status,
                'amount'      => $p->amount,
                'currency'    => $p->currency,
                'created_at'  => $p->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function disputesPayload(User $user): array
    {
        return $user->disputes()
            ->get(['id', 'contract_id', 'status', 'reason', 'created_at'])
            ->map(fn ($d) => [
                'id'          => $d->id,
                'contract_id' => $d->contract_id,
                'status'      => $d->status?->value ?? $d->status,
                'reason'      => $d->reason,
                'created_at'  => $d->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
