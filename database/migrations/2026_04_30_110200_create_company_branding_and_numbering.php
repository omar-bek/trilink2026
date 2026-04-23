<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_branding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();

            // Document look & feel. Applied on every buyer-generated PDF
            // so contracts / POs / invoices look like tenant stationery.
            $table->string('invoice_logo_path')->nullable();
            $table->string('email_logo_path')->nullable();
            $table->string('primary_color', 9)->nullable();   // hex incl #
            $table->string('accent_color', 9)->nullable();

            // Outbound email sender. null → platform default (Trilink
            // transactional mailer). When set, the mailer queue stamps
            // From/Reply-To with the tenant's verified domain.
            $table->string('email_from_name', 200)->nullable();
            $table->string('email_from_address', 200)->nullable();
            $table->boolean('email_sender_verified')->default(false);

            $table->text('invoice_footer_text')->nullable();
            $table->text('contract_footer_text')->nullable();
            $table->text('po_footer_text')->nullable();

            $table->timestamps();
        });

        Schema::create('company_document_numbering', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // What kind of document this series numbers — invoice, PO,
            // RFQ, contract, credit_note, etc.
            $table->string('document_type', 32);

            // Prefix + padded counter. e.g. "INV-{YEAR}-{SEQ:6}" →
            // INV-2026-000127. Year/month tokens are resolved at issue
            // time so fiscal-year rollover doesn't break sequences.
            $table->string('prefix', 32);
            $table->string('format_template', 64)->default('{PREFIX}-{YEAR}-{SEQ:6}');
            $table->unsignedInteger('current_sequence')->default(0);
            $table->unsignedSmallInteger('reset_year')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'document_type']);
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cost_centers')->nullOnDelete();

            // Human code (e.g. "MKT-01") + display name. Cost centres
            // form a tree so large groups can model divisions/units.
            $table->string('code', 32);
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();

            // Optional budget envelope per fiscal year. Committed amount
            // increments as POs/contracts reference the centre; the
            // dashboard shows remaining headroom so a buyer can't blow
            // the budget without a manager override.
            $table->decimal('annual_budget_aed', 18, 2)->nullable();
            $table->decimal('committed_aed', 18, 2)->default(0);
            $table->unsignedSmallInteger('fiscal_year')->nullable();

            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('company_document_numbering');
        Schema::dropIfExists('company_branding');
    }
};
