<?php

namespace App\Http\Controllers\Public;

use App\Enums\SignatureGrade;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\Signing\SignatureGradeResolver;
use Illuminate\View\View;

/**
 * Phase 6 (UAE Compliance Roadmap) — public signature verification.
 *
 * Anyone holding the contract URL (an inspector, a court clerk, the
 * counterparty's lawyer) can hit /contracts/{id}/verify and get a
 * structured page that:
 *
 *   1. Lists every signature on the contract with grade + provider
 *   2. Recomputes the contract content hash and compares it against
 *      the stored hash on each signature row — if any row's hash
 *      doesn't match, the contract has been tampered with after
 *      signing
 *   3. Surfaces the legal grade required vs achieved per signature
 *      so the inspector can see at a glance whether the signature
 *      is enforceable under Federal Decree-Law 46/2021
 *
 * Public endpoint — NO authentication. The contract URL is the
 * authorization mechanism (you can only verify what you can link
 * to). The view does NOT expose any commercial details (price, line
 * items, parties' contact info) — only the signature audit trail
 * and the contract number/hash.
 */
class SignatureVerifyController extends Controller
{
    public function __construct(
        private readonly SignatureGradeResolver $resolver,
    ) {
    }

    public function show(int $id): View
    {
        $contract = Contract::with('buyerCompany')->findOrFail($id);

        // Recompute the canonical content hash with the SAME recipe
        // ContractService::sign uses. Any drift between this and the
        // hash stored on a signature row means the contract has been
        // edited after signing.
        $currentHash = hash('sha256', json_encode([
            'contract_number' => $contract->contract_number,
            'version'         => $contract->version,
            'terms'           => $contract->terms,
            'amounts'         => $contract->amounts,
            'parties'         => $contract->parties,
        ], JSON_UNESCAPED_UNICODE));

        $required = $this->resolver->requiredFor($contract);

        $signatures = collect($contract->signatures ?? [])->map(function (array $row) use ($currentHash, $required) {
            $achievedRaw = (string) ($row['signature_grade'] ?? 'simple');
            $achieved = SignatureGrade::tryFrom($achievedRaw) ?? SignatureGrade::SIMPLE;

            return [
                'company_id'       => $row['company_id'] ?? null,
                'user_id'          => $row['user_id'] ?? null,
                'signed_at'        => $row['signed_at'] ?? null,
                'achieved_grade'   => $achieved->value,
                'achieved_label'   => $achieved->label(),
                'meets_required'   => $achieved->satisfies($required),
                'tsp_provider'     => $row['tsp_provider'] ?? null,
                'uae_pass_user_id' => $row['uae_pass_user_id'] ?? null,
                'hash_at_sign'     => $row['contract_hash'] ?? null,
                'hash_matches'     => isset($row['contract_hash']) && hash_equals((string) $row['contract_hash'], $currentHash),
                'ip_address'       => $row['ip_address'] ?? null,
            ];
        })->all();

        return view('public.contracts.verify', [
            'contract'         => $contract,
            'current_hash'     => $currentHash,
            'signatures'       => $signatures,
            'required_grade'   => $required->value,
            'required_label'   => $required->label(),
            'required_reason'  => $this->resolver->reasonFor($contract),
            'all_intact'       => collect($signatures)->every(fn ($s) => $s['hash_matches']),
            'all_meet_grade'   => collect($signatures)->every(fn ($s) => $s['meets_required']),
        ]);
    }
}
