<?php

/*
 * Audit log retention + chain verification settings.
 *
 * The audit log is a tamper-evident hash chain (Phase 0 of the UAE
 * Compliance Roadmap). The values below govern how long entries are kept
 * before they may be archived to immutable storage, and how the verify
 * command paginates when walking the chain on large tables.
 *
 * Retention is set to 7 years (2555 days) by default, the longest period
 * required across UAE federal laws that touch our records:
 *
 *   - Federal Decree-Law 50/2022 (Commercial Transactions Law) — books and
 *     records: 5 years from the end of the relevant financial period.
 *   - Federal Decree-Law 8/2017 (VAT) — tax records: 5 years (15 years for
 *     real-estate-related records).
 *   - Federal Decree-Law 20/2018 (AML/CFT) — customer due diligence and
 *     transaction records: 5 years from the end of the business relationship.
 *
 * Setting AUDIT_RETENTION_DAYS lower than 1825 (5 years) violates one or
 * more of those laws. Don't do it.
 */

return [

    'retention_days' => env('AUDIT_RETENTION_DAYS', 2555),

    'verify_chain_chunk' => env('AUDIT_VERIFY_CHUNK', 1000),

    /*
     * Which storage backend the archive command writes cold logs to.
     *
     * Phase 0 ships with no archive backend wired up — the command runs
     * in dry-run mode regardless of this setting. Phase 8 implements
     * S3 Object Lock + OpenTimestamps anchoring; until then, leave this
     * unset and the archival is a no-op.
     */
    'archive_backend' => env('AUDIT_ARCHIVE_BACKEND'),

    /*
     * When true, surfacing a chain mismatch in verify-chain raises a
     * notification to all admins (Phase 0 just prints to console; the
     * notification hook is added in Phase 8).
     */
    'alert_on_chain_break' => env('AUDIT_ALERT_ON_CHAIN_BREAK', false),

];
