<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Another migration (seen in a prior branch) already created
        // this schema — guard so we skip it cleanly instead of blowing
        // up the migrate run. Same for the two child tables below.
        if (Schema::hasTable('letters_of_credit')) {
            $this->ensureEvents();
            $this->ensureDrawings();

            return;
        }

        Schema::create('letters_of_credit', function (Blueprint $table) {
            $table->id();

            // An LC is always two parties + the contract it secures.
            // Applicant = buyer (pays premium, opens the LC).
            // Beneficiary = supplier (draws against it on presentation).
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('applicant_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('beneficiary_company_id')->constrained('companies')->cascadeOnDelete();

            // Reference assigned by the issuing bank. The LC number is
            // unique across UAE issuers because it includes the issuing
            // BIC + year + running sequence; we store whatever the bank
            // returns verbatim so the user can search for it.
            $table->string('lc_number', 64)->unique();
            $table->string('issuing_bank', 200);
            $table->string('issuing_bank_bic', 11)->nullable();
            $table->string('advising_bank', 200)->nullable();
            $table->string('advising_bank_bic', 11)->nullable();

            // UCP 600 form. Most UAE trade LCs are IRREVOCABLE; sight vs
            // usance controls when the beneficiary gets paid (at sight
            // on presentation, or X days after).
            $table->string('form', 32)->default('irrevocable');      // irrevocable|revocable|standby
            $table->string('payment_type', 32)->default('sight');    // sight|usance|mixed|deferred
            $table->unsignedSmallInteger('usance_days')->nullable();
            $table->boolean('transferable')->default(false);
            $table->boolean('confirmed')->default(false);

            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);

            // Tolerance margins (SWIFT field 39A "+/-%") — beneficiary
            // can draw up to amount + tolerance if the shipping weight
            // varies. Null = no tolerance.
            $table->unsignedTinyInteger('tolerance_percent_over')->nullable();
            $table->unsignedTinyInteger('tolerance_percent_under')->nullable();

            $table->date('issue_date');
            $table->date('expiry_date');
            $table->string('expiry_place', 100)->nullable();
            $table->date('latest_shipment_date')->nullable();

            // Incoterms + port of loading / discharge feed the
            // compliance checks on presented documents.
            $table->string('incoterm', 8)->nullable();
            $table->string('port_of_loading', 100)->nullable();
            $table->string('port_of_discharge', 100)->nullable();
            $table->text('goods_description')->nullable();
            $table->text('documents_required')->nullable();

            // Lifecycle: draft → issued → advised → amended → drawn →
            // closed → expired → cancelled. Each transition is recorded
            // in letter_of_credit_events below.
            $table->string('status', 32)->default('draft');

            $table->decimal('drawn_amount', 18, 2)->default(0);
            $table->decimal('available_amount', 18, 2);

            $table->string('advice_document_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['applicant_company_id', 'status']);
            $table->index(['beneficiary_company_id', 'status']);
        });

        $this->ensureEvents();
        $this->ensureDrawings();
    }

    private function ensureEvents(): void
    {
        if (Schema::hasTable('letter_of_credit_events')) {
            return;
        }

        Schema::create('letter_of_credit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_of_credit_id')->constrained('letters_of_credit')->cascadeOnDelete();
            $table->string('event', 64);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 18, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function ensureDrawings(): void
    {
        if (Schema::hasTable('letter_of_credit_drawings')) {
            return;
        }

        Schema::create('letter_of_credit_drawings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_of_credit_id')->constrained('letters_of_credit')->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->date('presentation_date');
            $table->date('honoured_date')->nullable();
            $table->foreignId('presented_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('discrepancies')->nullable();
            $table->string('status', 32)->default('presented');
            $table->string('document_bundle_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_of_credit_drawings');
        Schema::dropIfExists('letter_of_credit_events');
        Schema::dropIfExists('letters_of_credit');
    }
};
