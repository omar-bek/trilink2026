<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * sanctions_screenings — audit trail of every check we ran against
     * external sanctions watchlists (OFAC, UN, EU, OpenSanctions).
     *
     * One row per call, even when the result is "clean". This gives compliance
     * a defensible "we screened on date X and found nothing" record per
     * company, which is exactly what AML auditors look for.
     *
     * The matched_entities JSON snapshot pins the third-party data we used
     * to make the decision, so a later list update doesn't retroactively
     * rewrite history.
     */
    public function up(): void
    {
        Schema::create('sanctions_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('opensanctions');
            $table->string('query');
            $table->string('result', 16);
            $table->unsignedSmallInteger('match_count')->default(0);
            $table->json('matched_entities')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index('result');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('sanctions_status', 16)->default('not_screened')->after('verification_level');
            $table->timestamp('sanctions_screened_at')->nullable()->after('sanctions_status');
            $table->index('sanctions_status');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['sanctions_status', 'sanctions_screened_at']);
        });

        Schema::dropIfExists('sanctions_screenings');
    }
};
