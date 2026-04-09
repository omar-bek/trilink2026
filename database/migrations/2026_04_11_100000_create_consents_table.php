<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (UAE Compliance Roadmap) — PDPL consent ledger.
 *
 * Federal Decree-Law 45/2021 Article 6 requires that any processing of
 * personal data based on consent must be evidenced by a record showing:
 *
 *   - WHO consented (user_id)
 *   - WHAT they consented to (consent_type + version)
 *   - WHEN they consented (granted_at) and when they withdrew (withdrawn_at)
 *   - HOW the consent was captured (ip_address + user_agent — proves it
 *     was the data subject acting, not someone clicking on their behalf)
 *
 * This table is the authoritative ledger for DSAR responses ("show me
 * everything you've ever recorded about my consent") and for the breach-
 * notification analysis ("which users had granted marketing consent at
 * the time of the leak so we know who to notify first").
 *
 * Append-only: rows are NEVER deleted. Withdrawing consent inserts a new
 * row with `withdrawn_at` set, leaving the original `granted_at` row
 * untouched. This is required so the audit trail can answer "did this
 * user have consent at moment T?" — and that question becomes impossible
 * if we mutate or delete past rows.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();

            // The data subject. Cascade-on-delete so a hard-erasure of a
            // user (which itself is rare — see DataErasureService — but
            // legally required if requested via PDPL Article 15) takes
            // their consent ledger with them.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Discriminator. Drives the upsert logic in ConsentLedger.
            // Allowed values (enforced in PHP, not as DB enum, so we can
            // add new types without a migration):
            //   privacy_policy     — explicit acceptance of the policy
            //   data_processing    — DPA acceptance for B2B contracts
            //   cookies_essential  — strictly-necessary cookies (auto-granted)
            //   cookies_analytics  — optional analytics cookies
            //   marketing_email    — marketing emails / newsletters
            //   third_party_share  — sharing with named third parties
            $table->string('consent_type', 64);

            // Version of the document the user consented to. Bump this
            // every time the privacy policy / DPA wording changes — the
            // ledger will then know "user X agreed to v1.2 but the
            // current text is v1.4, we should re-prompt".
            $table->string('version', 16);

            $table->timestamp('granted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();

            // Capture context — proves the consent was real and freely
            // given. Both nullable in case the consent was set by an
            // admin on behalf of the user (e.g. backfill at PDPL
            // go-live for existing accounts).
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'consent_type'], 'idx_consents_user_type');
            $table->index('granted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
