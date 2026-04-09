<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.5 (UAE Compliance Roadmap — post-implementation hardening) —
 * privacy policy text snapshotting.
 *
 * The Phase 2 review surfaced that the consent ledger only records a
 * version string ('1.0', '1.1', ...). When the privacy policy is
 * re-published as v1.1 and the v1.0 text is removed from the blade,
 * there's no way to prove what the user actually consented to.
 *
 * The fix is an immutable archive of every published version: full
 * Arabic + English body, sha256 of the bytes, and the effective_from
 * date. Consents are linked to a row in this table by FK so the
 * DSAR archive can include the exact text the user agreed to,
 * regardless of how many versions ago it was published.
 *
 * This table is APPEND-ONLY in production. The admin can publish a
 * new version, but never edit or delete an existing row — the audit
 * trail depends on every previous text being preserved verbatim.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('privacy_policy_versions', function (Blueprint $table) {
            $table->id();

            // Semantic version string surfaced to users (e.g. '1.0').
            // Unique because the same version can't be published twice.
            $table->string('version', 16)->unique();

            // The full bilingual body — what the user actually saw on
            // the privacy policy page when they clicked Accept. Not
            // markdown, not template — the rendered text after locale
            // resolution. Stored verbatim because PDPL Article 6
            // evidence is "what was shown", not "what could have
            // been generated".
            $table->longText('body_en');
            $table->longText('body_ar');

            // SHA-256 of the canonical "ar||en" bytes — used to detect
            // accidental edits to the row in the DB outside the admin
            // publish flow.
            $table->string('sha256', 64);

            // First moment this version is in effect. The publishing
            // admin sets this; it can be in the future for scheduled
            // releases.
            $table->timestamp('effective_from');

            // Optional changelog summary for the admin's reference.
            $table->text('changelog')->nullable();

            $table->foreignId('published_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('effective_from');
        });

        // Link consents to the policy version they accepted. Nullable
        // for backwards compatibility — pre-Phase-2.5 rows have a
        // version string but no FK.
        Schema::table('consents', function (Blueprint $table) {
            $table->foreignId('privacy_policy_version_id')
                ->nullable()
                ->after('version')
                ->constrained('privacy_policy_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->dropForeign(['privacy_policy_version_id']);
            $table->dropColumn('privacy_policy_version_id');
        });
        Schema::dropIfExists('privacy_policy_versions');
    }
};
