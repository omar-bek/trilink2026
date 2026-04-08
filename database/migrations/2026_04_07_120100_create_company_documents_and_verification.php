<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * company_documents — the Document Vault. One row per uploaded file
     * (trade license, ISO cert, audited financials, etc.) with status,
     * expiry tracking, and a link back to the admin who verified it.
     *
     * Adds verification_level + verification metadata directly on companies
     * so badges and gates can be enforced everywhere with a single column
     * read.
     */
    public function up(): void
    {
        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('label')->nullable();
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('status', 32)->default('pending');
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'status']);
            $table->index('expires_at');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('verification_level', 32)->default('unverified')->after('status');
            $table->foreignId('verified_by')->nullable()->after('verification_level')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->index('verification_level');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['verification_level', 'verified_by', 'verified_at']);
        });

        Schema::dropIfExists('company_documents');
    }
};
