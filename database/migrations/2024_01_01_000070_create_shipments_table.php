<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logistics_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('status')->default('in_production');
            $table->json('origin')->nullable();
            $table->json('destination')->nullable();
            $table->json('current_location')->nullable();
            $table->string('inspection_status')->nullable();
            $table->string('customs_clearance_status')->nullable();
            $table->json('customs_documents')->nullable();
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamp('actual_delivery')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contract_id', 'status']);
            $table->index('company_id');
            $table->index('logistics_company_id');
            $table->index('customs_clearance_status');
            $table->index('inspection_status');
        });

        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->text('description')->nullable();
            $table->json('location')->nullable();
            $table->timestamp('event_at');
            $table->timestamps();

            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('shipments');
    }
};
