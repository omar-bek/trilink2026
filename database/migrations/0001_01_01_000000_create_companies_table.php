<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('registration_number')->unique();
            $table->string('type');
            $table->string('status')->default(CompanyStatus::PENDING->value);
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('logo')->nullable();
            $table->json('documents')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
