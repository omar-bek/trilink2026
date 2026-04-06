<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public registration form already collects `tax_number` and `website`,
 * but the original companies table doesn't have columns for them — so the
 * data is silently dropped on the floor at the controller boundary. Add
 * the columns so admins reviewing pending companies see the full picture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('tax_number', 100)->nullable()->after('registration_number');
            $table->string('website', 255)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['tax_number', 'website']);
        });
    }
};
