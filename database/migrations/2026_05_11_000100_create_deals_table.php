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
        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table
                ->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();
            $table
                ->foreignId('contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();
            $table
                ->foreignId('activity_id')
                ->nullable()
                ->constrained('activities')
                ->nullOnDelete();

            $table->string('name');
            $table->string('type', 50)->default('new_business');
            $table->string('status', 50)->default('lead');
            $table->string('source', 120)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedTinyInteger('probability')->default(10);
            $table->date('deal_at');
            $table->date('expected_close_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->date('next_follow_up_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('outcome', 1000)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'next_follow_up_at']);
            $table->index(['user_id', 'expected_close_at']);
            $table->index(['user_id', 'company_id']);
            $table->index(['user_id', 'contact_id']);
            $table->index(['user_id', 'activity_id']);
            $table->index(['user_id', 'status', 'sort_order', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
