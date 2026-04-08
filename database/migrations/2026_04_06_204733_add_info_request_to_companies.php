<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores an admin's "I need more info before approving you" request as a
 * JSON blob on the company. Structure:
 *
 *   {
 *     "items": ["tax_number", "trade_license_file", ...],
 *     "note":  "Free-text reason / instructions",
 *     "requested_at": "2026-04-06 18:30:00",
 *     "requested_by": <admin user id>
 *   }
 *
 * The presence of this column means the company manager will see a
 * "Complete missing information" form on their post-login holding page
 * (register.success). Once the user submits, the column is cleared and
 * the company goes back to plain pending review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->json('info_request')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('info_request');
        });
    }
};
