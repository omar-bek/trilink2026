<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wps_payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // MOHRE (Ministry of Human Resources & Emiratisation) labour
            // establishment identifier — every UAE employer has one and
            // it's the key the SIF file is signed against.
            $table->string('employer_eid', 32);

            // Agent bank that fronts the WPS rail on behalf of this
            // employer. Matches the issuer code assigned by CBUAE; see
            // WpsSifGenerator::buildRecord() for how it's stamped.
            $table->string('agent_id', 16);

            $table->date('pay_period_start');
            $table->date('pay_period_end');
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_gross_aed', 14, 2);
            $table->decimal('total_net_aed', 14, 2);

            // Lifecycle: draft → generated → submitted → settled → failed.
            // `generated` means the SIF file has been produced and hashed;
            // `submitted` means it's been uploaded to the bank; `settled`
            // means CBUAE has confirmed clearing.
            $table->string('status', 32)->default('draft');

            $table->string('sif_file_path')->nullable();
            $table->string('sif_file_hash', 128)->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->index(['company_id', 'pay_period_end']);
        });

        Schema::create('wps_payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('wps_payroll_batches')->cascadeOnDelete();

            // Employee identifiers required by the SIF spec — the Labour
            // Card Personal Number (LCPN) is unique per foreign worker,
            // the Employer Card Number links them to this establishment,
            // and the IBAN is where MOHRE expects the salary to land.
            $table->string('employee_lcpn', 32);
            $table->string('employee_name', 200);
            $table->string('iban', 50);
            $table->string('bank_code', 16)->nullable();

            $table->decimal('basic_salary', 12, 2);
            $table->decimal('housing_allowance', 12, 2)->default(0);
            $table->decimal('other_allowances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('gross_salary', 12, 2);
            $table->decimal('net_salary', 12, 2);

            $table->unsignedTinyInteger('leave_days')->default(0);
            $table->unsignedTinyInteger('working_days')->default(30);

            $table->timestamps();
            $table->index(['batch_id', 'employee_lcpn']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wps_payroll_lines');
        Schema::dropIfExists('wps_payroll_batches');
    }
};
