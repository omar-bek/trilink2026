<?php

namespace App\Services\Privacy;

use App\Models\Consent;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Notifications\DataExportReadyNotification;
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

        // Phase 2.5 (UAE Compliance Roadmap — post-implementation
        // hardening). PDPL Article 13(2) — "the data subject shall
        // have the right to obtain a copy of the personal data" — is
        // not satisfied by JSON metadata alone. The user has the right
        // to receive the actual files they uploaded (trade license,
        // beneficial owner ID copies, ICV certificates, etc.).
        //
        // The DSAR archive grows files into a /files/ tree organised
        // by source so the user (or their auditor) can locate any
        // single document immediately. Missing files are SKIPPED with
        // a manifest entry — never throw, since one missing PDF
        // shouldn't fail the whole DSAR fulfillment.
        $this->addUploadedFiles($zip, $user);

        $zip->close();

        // PDPL Article 13(2) — the data subject must be told their
        // export is available. Without this notification the archive
        // would sit on disk while the user wonders if their request
        // was even received.
        if ($request) {
            try {
                $user->notify(new DataExportReadyNotification($request));
            } catch (\Throwable $e) {
                \Log::warning('DataExportService notification failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $zipPath;
    }

    /**
     * Phase 2.5 — copy every PDPL-relevant file the user (or their
     * company) uploaded into the archive. Returns the manifest of
     * what was copied + what was missing, which the caller writes
     * into the index for completeness.
     */
    private function addUploadedFiles(ZipArchive $zip, User $user): array
    {
        $manifest = ['copied' => [], 'missing' => []];
        $disk = Storage::disk('local');

        $sources = [];

        // Company documents (trade license, VAT cert, MoA, etc.)
        if ($user->company) {
            $companyDocs = \App\Models\CompanyDocument::where('company_id', $user->company_id)->get();
            foreach ($companyDocs as $doc) {
                $sources[] = [
                    'category' => 'company-documents',
                    'label'    => ($doc->type?->value ?? 'document') . '-' . $doc->id,
                    'path'     => $doc->file_path,
                    'original' => $doc->original_filename,
                ];
            }

            // ICV certificates uploaded by the supplier
            $icvs = \App\Models\IcvCertificate::where('company_id', $user->company_id)->get();
            foreach ($icvs as $cert) {
                $sources[] = [
                    'category' => 'icv-certificates',
                    'label'    => $cert->issuer . '-' . $cert->certificate_number,
                    'path'     => $cert->file_path,
                    'original' => $cert->original_filename,
                ];
            }
        }

        // Tax invoices the user authored / received — only the PDFs
        // that match the user's company side. We don't dump every
        // tax invoice on the platform.
        if ($user->company_id) {
            $taxInvoices = \App\Models\TaxInvoice::query()
                ->where(function ($q) use ($user) {
                    $q->where('supplier_company_id', $user->company_id)
                      ->orWhere('buyer_company_id', $user->company_id);
                })
                ->whereNotNull('pdf_path')
                ->get();
            foreach ($taxInvoices as $inv) {
                $sources[] = [
                    'category' => 'tax-invoices',
                    'label'    => $inv->invoice_number,
                    'path'     => $inv->pdf_path,
                    'original' => $inv->invoice_number . '.pdf',
                ];
            }
        }

        foreach ($sources as $src) {
            if (empty($src['path']) || !$disk->exists($src['path'])) {
                $manifest['missing'][] = $src['category'] . '/' . $src['label'];
                continue;
            }

            // Sanitise the original filename — strip path components
            // an attacker may have stuffed into the upload form.
            $cleanName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($src['original'] ?? $src['label']));
            if ($cleanName === '' || $cleanName === '.') {
                $cleanName = $src['label'] . '.bin';
            }

            $archivePath = "files/{$src['category']}/{$src['label']}_{$cleanName}";
            $zip->addFromString($archivePath, $disk->get($src['path']) ?? '');
            $manifest['copied'][] = $archivePath;
        }

        // Drop the manifest into the archive itself so the user can
        // reconcile what's in /files/ with what was in their account.
        $zip->addFromString(
            'files/_manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $manifest;
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
            ->map(function (Consent $c) {
                $row = [
                    'id'           => $c->id,
                    'consent_type' => $c->consent_type,
                    'version'      => $c->version,
                    'granted_at'   => $c->granted_at?->toIso8601String(),
                    'withdrawn_at' => $c->withdrawn_at?->toIso8601String(),
                    'ip_address'   => $c->ip_address,
                    'user_agent'   => $c->user_agent,
                ];
                // Phase 2.5 — embed the exact policy text the user
                // agreed to so the DSAR is self-contained. PDPL Article
                // 13(2) requires the controller to make the policy
                // available "in a clear and transparent manner" — and
                // a JSON pointer to a URL that may have changed since
                // doesn't satisfy that.
                if ($c->privacy_policy_version_id) {
                    $version = \App\Models\PrivacyPolicyVersion::find($c->privacy_policy_version_id);
                    if ($version) {
                        $row['policy_snapshot'] = [
                            'version'        => $version->version,
                            'effective_from' => $version->effective_from?->toIso8601String(),
                            'sha256'         => $version->sha256,
                            'body_en'        => $version->body_en,
                            'body_ar'        => $version->body_ar,
                        ];
                    }
                }
                return $row;
            })
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
