<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 / task 1.9 — UNSPSC taxonomy support on the categories table.
 *
 * UNSPSC is a 4-level hierarchical product/service classification standard
 * used by SAP / Ariba / Coupa / Oracle and required by most enterprise
 * procurement integrations. The hierarchy:
 *
 *   Segment   (XX-00-00-00)  — top tier, ~57 buckets
 *   Family    (XX-XX-00-00)  — ~470 entries
 *   Class     (XX-XX-XX-00)  — ~5K entries
 *   Commodity (XX-XX-XX-XX)  — ~150K entries
 *
 * Storing them as four columns (instead of one packed string) means:
 *   - Filtering "everything under segment 41" is `WHERE unspsc_segment=41`
 *     and uses an index instead of `LIKE '41%'`.
 *   - Hierarchical browsing reads one column per drill-down level.
 *
 * The full 8-digit code is also kept (as `unspsc_code`) for cases where
 * we need an exact match (cXML punchout, EDI, ERP integration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            $t->string('unspsc_code', 16)->nullable()->after('name_ar');
            $t->unsignedInteger('unspsc_segment')->nullable()->after('unspsc_code');
            $t->unsignedInteger('unspsc_family')->nullable()->after('unspsc_segment');
            $t->unsignedInteger('unspsc_class')->nullable()->after('unspsc_family');
            $t->unsignedInteger('unspsc_commodity')->nullable()->after('unspsc_class');

            $t->index('unspsc_code', 'categories_unspsc_code_idx');
            $t->index('unspsc_segment', 'categories_unspsc_segment_idx');
            $t->index('unspsc_family', 'categories_unspsc_family_idx');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            $t->dropIndex('categories_unspsc_family_idx');
            $t->dropIndex('categories_unspsc_segment_idx');
            $t->dropIndex('categories_unspsc_code_idx');
            $t->dropColumn(['unspsc_code', 'unspsc_segment', 'unspsc_family', 'unspsc_class', 'unspsc_commodity']);
        });
    }
};
