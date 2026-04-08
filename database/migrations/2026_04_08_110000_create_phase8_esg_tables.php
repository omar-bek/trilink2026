<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — ESG & Sustainability schema. Three concerns share one
 * migration because they're all driven by the same questionnaire UX:
 *
 *   - esg_questionnaires: per-company answers across the three pillars
 *     (Environmental, Social, Governance). Pillar scores feed the ESG
 *     scorecard rendered on the company profile.
 *   - modern_slavery_statements: per-company self-attestation under the
 *     UK Modern Slavery Act + UAE Labour Law. Audit trail of every edit.
 *   - conflict_minerals_declarations: 3TG (tin, tungsten, tantalum, gold)
 *     statement + smelter list, modelled after the OECD Due Diligence
 *     Guidance schema.
 *   - carbon_footprints: aggregate carbon entries by entity (company,
 *     contract, shipment) so the ESG dashboard can roll up Scope 3
 *     emissions across the whole supply chain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_questionnaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Score 0-100 per pillar. Computed from the answers JSON
            // by EsgScoringService — never edited by hand.
            $table->unsignedTinyInteger('environmental_score')->default(0);
            $table->unsignedTinyInteger('social_score')->default(0);
            $table->unsignedTinyInteger('governance_score')->default(0);
            $table->unsignedTinyInteger('overall_score')->default(0);
            // Letter grade A-F derived from overall score. Stored
            // alongside the number so a UI sort by grade is cheap.
            $table->string('grade', 2)->default('F');
            // Q&A pairs keyed by question id. Each value is the answer
            // (number, boolean, or string depending on the question).
            $table->json('answers')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            // One questionnaire per company. Resubmission overwrites in
            // place — the audit log captures the diff for compliance.
            $table->unique('company_id');
            $table->index('grade');
        });

        Schema::create('modern_slavery_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->year('reporting_year');
            $table->text('statement');
            $table->json('controls')->nullable();
            $table->boolean('board_approved')->default(false);
            $table->date('approved_at')->nullable();
            $table->string('signed_by_name')->nullable();
            $table->string('signed_by_title')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'reporting_year']);
        });

        Schema::create('conflict_minerals_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->year('reporting_year');
            // Per-mineral status: 'conflict_free', 'in_progress', 'unknown'
            $table->string('tin_status', 20)->default('unknown');
            $table->string('tungsten_status', 20)->default('unknown');
            $table->string('tantalum_status', 20)->default('unknown');
            $table->string('gold_status', 20)->default('unknown');
            // Smelter list following the OECD CMRT template.
            $table->json('smelters')->nullable();
            $table->text('policy_url')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'reporting_year']);
        });

        Schema::create('carbon_footprints', function (Blueprint $table) {
            $table->id();
            // Polymorphic-ish: store entity type + id so the same table
            // can hold company-level, contract-level, and shipment-level
            // emissions without three separate tables.
            $table->string('entity_type', 20);
            $table->unsignedBigInteger('entity_id');
            // Scope 1 (direct), 2 (purchased energy), 3 (value chain).
            // Most TriLink rows are scope 3 (transport/embodied), but the
            // schema doesn't lock you in.
            $table->unsignedTinyInteger('scope')->default(3);
            $table->decimal('co2e_kg', 15, 2);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            // 'shipment_calculation', 'manual_entry', 'imported_certificate'
            $table->string('source', 30)->default('shipment_calculation');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('period_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carbon_footprints');
        Schema::dropIfExists('conflict_minerals_declarations');
        Schema::dropIfExists('modern_slavery_statements');
        Schema::dropIfExists('esg_questionnaires');
    }
};
