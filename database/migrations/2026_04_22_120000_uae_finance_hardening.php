<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UAE B2B finance hardening — Phase A→H foundations.
 *
 * Written defensively (every create / add guarded) so a partial run on
 * a dev DB can be re-applied without manual cleanup.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Phase A — Bank Guarantees ──────────────────────────────
        if (! Schema::hasTable('bank_guarantees')) {
            Schema::create('bank_guarantees', function (Blueprint $table) {
                $table->id();
                $table->string('bg_number', 50)->unique();
                $table->string('type', 32);
                $table->string('governing_rules', 16)->default('URDG_758');
                $table->foreignId('applicant_company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('beneficiary_company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('issuing_bank_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->string('issuing_bank_name', 150)->nullable();
                $table->string('issuing_bank_swift', 16)->nullable();
                $table->string('issuing_bank_reference', 100)->nullable();
                $table->foreignId('rfq_id')->nullable()->constrained('rfqs')->nullOnDelete();
                $table->foreignId('bid_id')->nullable()->constrained('bids')->nullOnDelete();
                $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
                $table->decimal('amount', 18, 2);
                $table->string('currency', 3)->default('AED');
                $table->decimal('percentage_of_base', 5, 2)->nullable();
                $table->decimal('base_amount', 18, 2)->nullable();
                $table->date('validity_start_date');
                $table->date('expiry_date');
                $table->unsignedSmallInteger('claim_period_days')->default(30);
                $table->string('status', 24)->default('pending_issuance');
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->string('advice_document_path', 255)->nullable();
                $table->string('advice_document_hash', 64)->nullable();
                $table->decimal('amount_remaining', 18, 2)->nullable();
                $table->decimal('amount_called', 18, 2)->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['applicant_company_id', 'status']);
                $table->index(['beneficiary_company_id', 'status']);
                $table->index('contract_id');
                $table->index('rfq_id');
                $table->index('expiry_date');
                $table->index('type');
            });
        }

        if (! Schema::hasTable('bank_guarantee_calls')) {
            Schema::create('bank_guarantee_calls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_guarantee_id')->constrained()->cascadeOnDelete();
                $table->foreignId('called_by_company_id')->constrained('companies');
                $table->foreignId('called_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount', 18, 2);
                $table->string('currency', 3);
                $table->text('reason');
                $table->string('claim_document_path', 255)->nullable();
                $table->string('status', 20)->default('submitted');
                $table->timestamp('honoured_at')->nullable();
                $table->string('bank_reference', 100)->nullable();
                $table->text('bank_response')->nullable();
                $table->timestamps();

                $table->index(['bank_guarantee_id', 'status']);
            });
        }

        if (! Schema::hasTable('bank_guarantee_events')) {
            Schema::create('bank_guarantee_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_guarantee_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event', 48);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['bank_guarantee_id', 'created_at']);
            });
        }

        // ── Phase B — Dispute ↔ Escrow freeze ──────────────────────
        Schema::table('escrow_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('escrow_accounts', 'frozen_at')) {
                $table->timestamp('frozen_at')->nullable()->after('activated_at');
            }
            if (! Schema::hasColumn('escrow_accounts', 'frozen_by_dispute_id')) {
                $table->foreignId('frozen_by_dispute_id')->nullable()->after('frozen_at')
                    ->constrained('disputes')->nullOnDelete();
            }
            if (! Schema::hasColumn('escrow_accounts', 'freeze_reason')) {
                $table->text('freeze_reason')->nullable()->after('frozen_by_dispute_id');
            }
        });

        // ── Phase C, G, H — Contract columns ───────────────────────
        Schema::table('contracts', function (Blueprint $table) {
            $cols = [
                'payment_terms' => fn ($t) => $t->string('payment_terms', 24)->default('net_30')->after('currency'),
                'late_fee_annual_rate' => fn ($t) => $t->decimal('late_fee_annual_rate', 5, 2)->nullable()->after('payment_terms'),
                'early_discount_rate' => fn ($t) => $t->decimal('early_discount_rate', 5, 2)->nullable()->after('late_fee_annual_rate'),
                'early_discount_days' => fn ($t) => $t->unsignedSmallInteger('early_discount_days')->nullable()->after('early_discount_rate'),
                'credit_limit' => fn ($t) => $t->decimal('credit_limit', 18, 2)->nullable()->after('early_discount_days'),
                'retention_percentage' => fn ($t) => $t->decimal('retention_percentage', 5, 2)->nullable()->after('credit_limit'),
                'retention_amount' => fn ($t) => $t->decimal('retention_amount', 18, 2)->nullable()->after('retention_percentage'),
                'retention_release_date' => fn ($t) => $t->date('retention_release_date')->nullable()->after('retention_amount'),
                'retention_released_at' => fn ($t) => $t->timestamp('retention_released_at')->nullable()->after('retention_release_date'),
                'vat_treatment' => fn ($t) => $t->string('vat_treatment', 24)->default('standard')->after('retention_released_at'),
                'corporate_tax_applicable' => fn ($t) => $t->boolean('corporate_tax_applicable')->default(true)->after('vat_treatment'),
                'default_wht_rate' => fn ($t) => $t->decimal('default_wht_rate', 5, 2)->nullable()->after('corporate_tax_applicable'),
            ];
            foreach ($cols as $name => $fn) {
                if (! Schema::hasColumn('contracts', $name)) {
                    $fn($table);
                }
            }
        });

        // ── Phase C, D, H — Payment columns ────────────────────────
        Schema::table('payments', function (Blueprint $table) {
            $cols = [
                'invoice_issued_at' => fn ($t) => $t->date('invoice_issued_at')->nullable()->after('approved_by'),
                'late_fee_amount' => fn ($t) => $t->decimal('late_fee_amount', 15, 2)->default(0)->after('invoice_issued_at'),
                'early_discount_amount' => fn ($t) => $t->decimal('early_discount_amount', 15, 2)->default(0)->after('late_fee_amount'),
                'wht_rate' => fn ($t) => $t->decimal('wht_rate', 5, 2)->nullable()->after('vat_rate'),
                'wht_amount' => fn ($t) => $t->decimal('wht_amount', 15, 2)->default(0)->after('wht_rate'),
                'vat_reverse_charge' => fn ($t) => $t->boolean('vat_reverse_charge')->default(false)->after('wht_amount'),
                'rail' => fn ($t) => $t->string('rail', 24)->nullable()->after('payment_gateway'),
                'uetr' => fn ($t) => $t->string('uetr', 36)->nullable()->after('rail'),
            ];
            foreach ($cols as $name => $fn) {
                if (! Schema::hasColumn('payments', $name)) {
                    $fn($table);
                }
            }
        });

        // ── Phase E — Payment screenings ───────────────────────────
        if (! Schema::hasTable('payment_screenings')) {
            Schema::create('payment_screenings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
                $table->string('stage', 24);
                $table->string('result', 16);
                $table->string('screened_entity', 32);
                $table->foreignId('screened_company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->json('findings')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();

                $table->index(['payment_id', 'stage']);
                $table->index('result');
            });
        }

        // ── Phase F — Bank statements ──────────────────────────────
        if (! Schema::hasTable('bank_statements')) {
            Schema::create('bank_statements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->string('account_identifier', 100);
                $table->string('currency', 3);
                $table->string('format', 16);
                $table->date('statement_date');
                $table->decimal('opening_balance', 18, 2);
                $table->decimal('closing_balance', 18, 2);
                $table->string('source_file', 255)->nullable();
                $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['account_identifier', 'statement_date']);
            });
        }

        if (! Schema::hasTable('bank_statement_lines')) {
            Schema::create('bank_statement_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_statement_id')->constrained()->cascadeOnDelete();
                $table->date('value_date');
                $table->date('booking_date')->nullable();
                $table->decimal('amount', 18, 2);
                $table->string('currency', 3);
                $table->string('direction', 8);
                $table->string('counterparty_name', 255)->nullable();
                $table->string('counterparty_iban', 50)->nullable();
                $table->string('reference', 255)->nullable();
                $table->string('description', 500)->nullable();
                $table->string('matched_type', 32)->nullable();
                $table->unsignedBigInteger('matched_id')->nullable();
                $table->string('match_status', 16)->default('unmatched');
                $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('matched_at')->nullable();
                $table->timestamps();

                $table->index(['match_status']);
                $table->index(['matched_type', 'matched_id']);
                $table->index('reference');
            });
        }

        // ── Phase G — UAE public holidays ──────────────────────────
        if (! Schema::hasTable('uae_holidays')) {
            Schema::create('uae_holidays', function (Blueprint $table) {
                $table->id();
                $table->date('holiday_date')->unique();
                $table->string('name', 100);
                $table->string('name_ar', 100)->nullable();
                $table->string('scope', 24)->default('federal');
                $table->string('confirmation', 16)->default('confirmed');
                $table->timestamps();

                $table->index('scope');
            });

            $holidays = [
                ['2026-01-01', 'New Year Day', 'رأس السنة الميلادية'],
                ['2026-03-20', 'Eid Al Fitr Holiday', 'عطلة عيد الفطر'],
                ['2026-03-21', 'Eid Al Fitr Holiday', 'عطلة عيد الفطر'],
                ['2026-03-22', 'Eid Al Fitr Holiday', 'عطلة عيد الفطر'],
                ['2026-05-27', 'Arafat Day', 'يوم عرفة'],
                ['2026-05-28', 'Eid Al Adha', 'عيد الأضحى'],
                ['2026-05-29', 'Eid Al Adha Holiday', 'عطلة عيد الأضحى'],
                ['2026-05-30', 'Eid Al Adha Holiday', 'عطلة عيد الأضحى'],
                ['2026-06-16', 'Islamic New Year', 'رأس السنة الهجرية'],
                ['2026-08-25', 'Prophet Muhammad Birthday', 'المولد النبوي'],
                ['2026-12-01', 'Commemoration Day', 'يوم الشهيد'],
                ['2026-12-02', 'National Day', 'اليوم الوطني'],
                ['2026-12-03', 'National Day Holiday', 'عطلة اليوم الوطني'],
            ];
            foreach ($holidays as [$d, $n, $na]) {
                \Illuminate\Support\Facades\DB::table('uae_holidays')->insertOrIgnore([
                    'holiday_date' => $d,
                    'name' => $n,
                    'name_ar' => $na,
                    'scope' => 'federal',
                    'confirmation' => 'confirmed',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('uae_holidays');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('payment_screenings');
        Schema::dropIfExists('bank_guarantee_events');
        Schema::dropIfExists('bank_guarantee_calls');
        Schema::dropIfExists('bank_guarantees');
    }
};
