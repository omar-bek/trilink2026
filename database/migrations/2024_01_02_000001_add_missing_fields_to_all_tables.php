<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users: split name into first_name/last_name, add lastLogin
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'first_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name')->after('first_name');
            $table->timestamp('last_login')->nullable()->after('status');
        });

        // Purchase Requests: add approver_id
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreignId('approver_id')->nullable()->after('buyer_id')->constrained('users')->nullOnDelete();
        });

        // RFQs: add missing fields
        Schema::table('rfqs', function (Blueprint $table) {
            $table->string('target_company_type')->nullable()->after('target_role');
            $table->json('attachments')->nullable()->after('delivery_location');
            $table->date('required_delivery_date')->nullable()->after('deadline');
        });

        // Bids: add missing fields
        Schema::table('bids', function (Blueprint $table) {
            $table->timestamp('delivery_date')->nullable()->after('delivery_time_days');
            $table->json('ai_score_metadata')->nullable()->after('ai_score');
        });

        // Payments: add all missing fields
        Schema::table('payments', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('milestone');
            $table->timestamp('paid_date')->nullable()->after('due_date');
            $table->string('payment_method')->nullable()->after('payment_gateway');
            $table->string('transaction_id')->nullable()->after('gateway_order_id');
            $table->string('gateway_client_secret')->nullable()->after('transaction_id');
            $table->string('gateway_redirect_url')->nullable()->after('gateway_client_secret');
            $table->timestamp('failed_at')->nullable()->after('retry_count');
            $table->text('failure_reason')->nullable()->after('failed_at');
            $table->timestamp('last_retry_at')->nullable()->after('failure_reason');
            $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
        });

        // Shipments: add missing fields
        Schema::table('shipments', function (Blueprint $table) {
            $table->text('inspection_rejection_reason')->nullable()->after('inspection_status');
            $table->unsignedSmallInteger('customs_resubmission_count')->default(0)->after('customs_documents');
            $table->timestamp('customs_cleared_at')->nullable()->after('customs_resubmission_count');
            $table->foreignId('customs_cleared_by')->nullable()->after('customs_cleared_at')->constrained('users')->nullOnDelete();
            $table->string('customs_authority')->nullable()->after('customs_cleared_by');
        });

        // Disputes: add missing fields
        Schema::table('disputes', function (Blueprint $table) {
            $table->text('government_notes')->nullable()->after('resolution');
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->foreignId('assigned_by')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('response_time')->nullable()->after('sla_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['government_notes', 'assigned_at', 'assigned_by', 'response_time']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'inspection_rejection_reason', 'customs_resubmission_count',
                'customs_cleared_at', 'customs_cleared_by', 'customs_authority',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'due_date', 'paid_date', 'payment_method', 'transaction_id',
                'gateway_client_secret', 'gateway_redirect_url', 'failed_at',
                'failure_reason', 'last_retry_at', 'rejected_at', 'rejected_by',
            ]);
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->dropColumn(['delivery_date', 'ai_score_metadata']);
        });

        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn(['target_company_type', 'attachments', 'required_delivery_date']);
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('approver_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_name', 'last_login']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('first_name', 'name');
        });
    }
};
