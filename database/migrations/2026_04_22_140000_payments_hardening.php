<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments hardening — UAE B2B finance Phase A/B/C.
 *
 * Adds the infrastructure the payment subsystem was missing:
 *
 *   - FX lock on Payment (amount & rate frozen at approval, so the value
 *     that leaves the buyer's hand never drifts with the market).
 *   - Dual approval ledger (payment_approvals) so payments above an AED
 *     threshold require a second signer.
 *   - Post-dated cheque tracking (postdated_cheques + cheque_events) —
 *     the backbone of UAE B2B settlement we were missing entirely.
 *   - Credit-note auto-link from refunds (payments.refund_credit_note_id).
 *   - Bank reconciliation period closure (bank_reconciliation_periods) so
 *     a month cannot be closed while exceptions remain.
 *   - Platform fee allocation rows per payment.
 *   - VAT return period tagging on tax invoices (q1/q2/q3/q4 + year).
 *   - Payment dispute window (payments.dispute_window_days, disputed_at).
 *   - Corporate Tax exposure columns on Payment.
 *
 * Defensive: every create / add is guarded so re-running on a partly-
 * migrated dev DB is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Payments: FX lock, CT, dispute, cheque ref, CN link ───────
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (! Schema::hasColumn('payments', 'fx_rate_snapshot')) {
                    $table->decimal('fx_rate_snapshot', 18, 8)->nullable()->after('currency');
                }
                if (! Schema::hasColumn('payments', 'fx_base_currency')) {
                    $table->string('fx_base_currency', 3)->nullable()->after('fx_rate_snapshot');
                }
                if (! Schema::hasColumn('payments', 'fx_locked_at')) {
                    $table->timestamp('fx_locked_at')->nullable()->after('fx_base_currency');
                }
                if (! Schema::hasColumn('payments', 'amount_in_base')) {
                    $table->decimal('amount_in_base', 18, 2)->nullable()->after('fx_locked_at');
                }
                if (! Schema::hasColumn('payments', 'corporate_tax_applicable')) {
                    $table->boolean('corporate_tax_applicable')->default(false)->after('vat_reverse_charge');
                }
                if (! Schema::hasColumn('payments', 'corporate_tax_rate')) {
                    $table->decimal('corporate_tax_rate', 5, 2)->nullable()->after('corporate_tax_applicable');
                }
                if (! Schema::hasColumn('payments', 'corporate_tax_amount')) {
                    $table->decimal('corporate_tax_amount', 18, 2)->nullable()->after('corporate_tax_rate');
                }
                if (! Schema::hasColumn('payments', 'requires_dual_approval')) {
                    $table->boolean('requires_dual_approval')->default(false)->after('approved_by');
                }
                if (! Schema::hasColumn('payments', 'second_approver_id')) {
                    $table->foreignId('second_approver_id')->nullable()->after('requires_dual_approval')
                        ->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'second_approved_at')) {
                    $table->timestamp('second_approved_at')->nullable()->after('second_approver_id');
                }
                if (! Schema::hasColumn('payments', 'dispute_window_days')) {
                    $table->unsignedSmallInteger('dispute_window_days')->nullable()->after('paid_date');
                }
                if (! Schema::hasColumn('payments', 'disputed_at')) {
                    $table->timestamp('disputed_at')->nullable()->after('dispute_window_days');
                }
                if (! Schema::hasColumn('payments', 'dispute_reason')) {
                    $table->string('dispute_reason', 500)->nullable()->after('disputed_at');
                }
                if (! Schema::hasColumn('payments', 'postdated_cheque_id')) {
                    $table->unsignedBigInteger('postdated_cheque_id')->nullable()->after('uetr');
                    $table->index('postdated_cheque_id');
                }
                if (! Schema::hasColumn('payments', 'refund_credit_note_id')) {
                    $table->foreignId('refund_credit_note_id')->nullable()->after('postdated_cheque_id')
                        ->constrained('tax_credit_notes')->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'settled_at')) {
                    $table->timestamp('settled_at')->nullable()->after('refund_credit_note_id');
                }
                if (! Schema::hasColumn('payments', 'is_late_fee_accrual')) {
                    $table->boolean('is_late_fee_accrual')->default(false)->after('late_fee_amount');
                }
                if (! Schema::hasColumn('payments', 'parent_payment_id')) {
                    $table->foreignId('parent_payment_id')->nullable()->after('is_late_fee_accrual')
                        ->constrained('payments')->nullOnDelete();
                }
            });
        }

        // ── payment_approvals (dual-approval ledger) ─────────────────
        if (! Schema::hasTable('payment_approvals')) {
            Schema::create('payment_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 24); // 'primary' | 'secondary'
                $table->string('action', 16); // 'approved' | 'rejected'
                $table->text('notes')->nullable();
                $table->decimal('amount_snapshot', 18, 2)->nullable();
                $table->string('currency_snapshot', 3)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamps();

                $table->index(['payment_id', 'role']);
                $table->unique(['payment_id', 'approver_id', 'role']);
            });
        }

        // ── postdated_cheques ───────────────────────────────────────
        if (! Schema::hasTable('postdated_cheques')) {
            Schema::create('postdated_cheques', function (Blueprint $table) {
                $table->id();
                $table->string('cheque_number', 32);
                $table->foreignId('issuer_company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('beneficiary_company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->string('drawer_bank_name', 150);
                $table->string('drawer_bank_swift', 16)->nullable();
                $table->string('drawer_account_iban', 34)->nullable();
                $table->date('issue_date');
                $table->date('presentation_date'); // the "post-dated" part
                $table->decimal('amount', 18, 2);
                $table->string('currency', 3)->default('AED');
                $table->string('status', 24)->default('issued');
                // issued | deposited | cleared | returned | stopped | replaced
                $table->string('return_reason', 64)->nullable();
                $table->string('image_path', 255)->nullable();
                $table->string('image_sha256', 64)->nullable();
                $table->timestamp('deposited_at')->nullable();
                $table->timestamp('cleared_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['issuer_company_id', 'status']);
                $table->index(['beneficiary_company_id', 'status']);
                $table->index(['presentation_date', 'status']);
                $table->unique(['drawer_bank_name', 'cheque_number', 'issuer_company_id'], 'uq_cheque_number');
            });
        }

        if (! Schema::hasTable('cheque_events')) {
            Schema::create('cheque_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('postdated_cheque_id')->constrained('postdated_cheques')->cascadeOnDelete();
                $table->string('event', 32); // issued | deposited | cleared | returned | stopped | replaced
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['postdated_cheque_id', 'event']);
            });
        }

        // ── bank_reconciliation_periods (closure gate) ───────────────
        if (! Schema::hasTable('bank_reconciliation_periods')) {
            Schema::create('bank_reconciliation_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 16)->default('open'); // open | closed
                $table->unsignedInteger('lines_matched')->default(0);
                $table->unsignedInteger('lines_unmatched')->default(0);
                $table->decimal('opening_balance', 18, 2)->nullable();
                $table->decimal('closing_balance', 18, 2)->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('closure_notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'period_start', 'period_end'], 'uq_recon_period_window');
                $table->index(['company_id', 'status']);
            });
        }

        // ── platform_fee_allocations (per-payment fee ledger) ────────
        if (! Schema::hasTable('platform_fee_allocations')) {
            Schema::create('platform_fee_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->string('fee_type', 32); // transaction | escrow | recon | listing
                $table->decimal('base_amount', 18, 2);
                $table->decimal('rate', 6, 4); // e.g. 0.0125 for 1.25%
                $table->decimal('fee_amount', 18, 2);
                $table->string('currency', 3)->default('AED');
                $table->timestamps();

                $table->index(['payment_id', 'fee_type']);
            });
        }

        // ── tax_invoices: VAT return period tag ─────────────────────
        if (Schema::hasTable('tax_invoices')) {
            Schema::table('tax_invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('tax_invoices', 'vat_return_period')) {
                    $table->string('vat_return_period', 8)->nullable()->after('vat_treatment');
                    // Format: YYYY-Q# (e.g. 2026-Q2) or YYYY-MM (if monthly filer).
                    $table->index('vat_return_period');
                }
            });
        }

        // ── contracts: payment SLA + schedule validation flag ───────
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (! Schema::hasColumn('contracts', 'payment_sla_days')) {
                    $table->unsignedSmallInteger('payment_sla_days')->nullable()->after('payment_terms');
                }
                if (! Schema::hasColumn('contracts', 'schedule_validated_at')) {
                    $table->timestamp('schedule_validated_at')->nullable()->after('payment_sla_days');
                }
                if (! Schema::hasColumn('contracts', 'dual_approval_threshold_aed')) {
                    $table->decimal('dual_approval_threshold_aed', 18, 2)->nullable()->after('credit_limit');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['platform_fee_allocations', 'bank_reconciliation_periods', 'cheque_events', 'postdated_cheques', 'payment_approvals'] as $t) {
            Schema::dropIfExists($t);
        }

        if (Schema::hasTable('tax_invoices') && Schema::hasColumn('tax_invoices', 'vat_return_period')) {
            Schema::table('tax_invoices', function (Blueprint $table) {
                $table->dropColumn('vat_return_period');
            });
        }

        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                foreach (['dual_approval_threshold_aed', 'schedule_validated_at', 'payment_sla_days'] as $col) {
                    if (Schema::hasColumn('contracts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                foreach ([
                    'parent_payment_id' => true,
                    'is_late_fee_accrual' => false,
                    'settled_at' => false,
                    'refund_credit_note_id' => true,
                    'postdated_cheque_id' => false,
                    'dispute_reason' => false,
                    'disputed_at' => false,
                    'dispute_window_days' => false,
                    'second_approved_at' => false,
                    'second_approver_id' => true,
                    'requires_dual_approval' => false,
                    'corporate_tax_amount' => false,
                    'corporate_tax_rate' => false,
                    'corporate_tax_applicable' => false,
                    'amount_in_base' => false,
                    'fx_locked_at' => false,
                    'fx_base_currency' => false,
                    'fx_rate_snapshot' => false,
                ] as $col => $fk) {
                    if (Schema::hasColumn('payments', $col)) {
                        if ($fk) {
                            $table->dropConstrainedForeignId($col);
                        } else {
                            $table->dropColumn($col);
                        }
                    }
                }
            });
        }
    }
};
