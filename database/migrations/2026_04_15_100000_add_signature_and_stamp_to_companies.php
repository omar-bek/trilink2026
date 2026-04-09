<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the company-level signature and stamp image paths used by the
 * contract signing flow. Stored on the public disk so the contract show
 * page and the PDF render can both reach them via a normal asset URL.
 *
 * Both columns are nullable because legacy companies registered before
 * this migration (and any company created via API/seed without an
 * upload step) need to be able to read/write the company without being
 * forced to provide these assets up front. The contract sign endpoint
 * is the gate that enforces "signature + stamp must exist before you
 * can sign" — see ContractController::sign().
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('logo');
            $table->string('stamp_path')->nullable()->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['signature_path', 'stamp_path']);
        });
    }
};
