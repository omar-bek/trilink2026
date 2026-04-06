<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the columns needed for in-company team management:
 *
 * - position_title:    free-text job title (e.g. "Senior Buyer", "AP Clerk")
 * - additional_roles:  JSON list of secondary role keys so a single user can
 *                      legitimately act as buyer + supplier, etc.
 * - permissions:       JSON map of permission keys (separate from
 *                      custom_permissions which is already used for
 *                      notification preferences — keeping concerns split).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('position_title')->nullable()->after('role');
            $table->json('additional_roles')->nullable()->after('position_title');
            $table->json('permissions')->nullable()->after('additional_roles');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['position_title', 'additional_roles', 'permissions']);
        });
    }
};
