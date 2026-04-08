<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback / reviews between companies after a contract completes.
 *
 * - `rater_company_id` is the company leaving the review (typically the buyer
 *   rating the supplier, but it's symmetric so suppliers can also rate buyers).
 * - `target_company_id` is the company being rated.
 * - `contract_id` scopes the review to a specific transaction so a rater can
 *   only leave one review per contract per direction.
 * - `rating` is 1-5.
 * - `comment` is the free-text review body.
 *
 * The PerformanceController, BidController (supplier card), and RfqController
 * (buyer card) all read from this table via
 * `DB::table('feedback')->where('target_company_id', ...)->avg('rating')`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('target_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('rater_user_id')->constrained('users');
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('comment')->nullable();
            // Breakdown scores (optional) so we can compute dimension averages.
            $table->unsignedTinyInteger('quality_score')->nullable();     // 1..5
            $table->unsignedTinyInteger('on_time_score')->nullable();     // 1..5
            $table->unsignedTinyInteger('communication_score')->nullable(); // 1..5
            $table->timestamps();

            // One review per contract per direction — prevents a buyer from
            // stuffing the supplier's rating with multiple entries.
            $table->unique(['contract_id', 'rater_company_id'], 'feedback_contract_rater_unique');
            $table->index('target_company_id');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
