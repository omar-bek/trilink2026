<?php

namespace App\Enums;

/**
 * Phase 6 (UAE Compliance Roadmap) — Federal Decree-Law 46/2021 on
 * Electronic Transactions and Trust Services categorises every
 * electronic signature into one of three grades:
 *
 *   - SIMPLE     — basic e-signature. Adequate for ordinary B2B
 *                  contracts. Captured by IP/UA + step-up auth +
 *                  consent text.
 *   - ADVANCED   — uniquely linked to the signatory, capable of
 *                  identifying them, created with means under their
 *                  sole control, and tamper-evident. The platform
 *                  satisfies this when the signature payload includes
 *                  a UAE Pass identity assertion or a TSP-issued
 *                  certificate.
 *   - QUALIFIED  — Advanced + the signing certificate was issued by
 *                  a Trust Service Provider accredited by TDRA. This
 *                  is the highest grade and is mandatory for
 *                  government contracts, real-estate transactions,
 *                  insurance contracts and any contract where a
 *                  domestic UAE court would refuse a Simple signature.
 *
 * The grade required for a given contract is decided by
 * {@see \App\Services\Signing\SignatureGradeResolver} based on
 * counterparty type, contract value, and category.
 *
 * The grade ACHIEVED on each signature row is stamped at sign time
 * by ContractService::sign — see the contract's `signatures` JSON
 * column for the per-row record.
 */
enum SignatureGrade: string
{
    case SIMPLE    = 'simple';
    case ADVANCED  = 'advanced';
    case QUALIFIED = 'qualified';

    public function label(): string
    {
        return match ($this) {
            self::SIMPLE    => 'Simple Electronic Signature',
            self::ADVANCED  => 'Advanced Electronic Signature',
            self::QUALIFIED => 'Qualified Electronic Signature',
        };
    }

    /**
     * The legal article this grade satisfies under Federal Decree-Law
     * 46/2021. Surfaced in the public verify page so an inspector can
     * see at a glance which standard the signature meets.
     */
    public function legalReference(): string
    {
        return match ($this) {
            self::SIMPLE    => 'Article 17 — Electronic Signature',
            self::ADVANCED  => 'Article 18 — Advanced Electronic Signature',
            self::QUALIFIED => 'Article 19 — Qualified Electronic Signature',
        };
    }

    /**
     * Numeric rank used for "achieved >= required" comparisons. The
     * higher the number, the stronger the signature.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SIMPLE    => 1,
            self::ADVANCED  => 2,
            self::QUALIFIED => 3,
        };
    }

    /**
     * True when this grade is at least as strong as the requested
     * minimum. The platform's sign action uses this to refuse a
     * Simple signature on a contract that requires Advanced or
     * Qualified.
     */
    public function satisfies(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }
}
