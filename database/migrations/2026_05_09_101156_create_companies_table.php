<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('status', 50)->default('lead');
            $table->string('industry', 120)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('ownership_type', 120)->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();

            $table->unsignedInteger('employee_count')->nullable();
            $table->decimal('annual_revenue', 15, 2)->nullable();

            $table->string('website')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('email')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('support_phone', 50)->nullable();
            $table->string('timezone', 120)->nullable();
            $table->string('preferred_contact_method', 50)->nullable();
            $table->string('tax_id', 100)->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('country', 120)->nullable();

            $table->date('last_contacted_at')->nullable();
            $table->date('next_follow_up_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'next_follow_up_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
