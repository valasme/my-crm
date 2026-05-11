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
        Schema::table('companies', function (Blueprint $table): void {
            $table
                ->foreignId('primary_contact_id')
                ->nullable()
                ->after('user_id')
                ->constrained('contacts')
                ->nullOnDelete();

            $table->index(
                ['user_id', 'primary_contact_id'],
                'companies_user_primary_contact_id_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropIndex('companies_user_primary_contact_id_index');
            $table->dropConstrainedForeignId('primary_contact_id');
        });
    }
};
