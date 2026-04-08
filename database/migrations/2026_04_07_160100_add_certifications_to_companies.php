<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `certifications` JSON column to the companies table for storing
 * supplier-side compliance badges (ISO 9001, CE, ESMA, Halal, etc.). Each
 * entry is `{name, issuer, expires_at, document_path}`.
 *
 * Kept separate from `documents` because documents are buyer-uploaded
 * verification files, while certifications are public-facing badges shown
 * on supplier profile cards and in bid evaluation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->json('certifications')->nullable()->after('documents');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('certifications');
        });
    }
};
