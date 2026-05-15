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
        Schema::table('contacts', function (Blueprint $table): void {
            $table->unique(['user_id', 'id'], 'contacts_user_id_id_unique');
            $table->unique(
                ['company_id', 'id'],
                'contacts_company_id_id_unique',
            );
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table
                ->foreign(
                    ['user_id', 'primary_contact_id'],
                    'companies_primary_contact_owner_fk',
                )
                ->references(['user_id', 'id'])
                ->on('contacts');

            $table
                ->foreign(
                    ['id', 'primary_contact_id'],
                    'companies_primary_contact_company_fk',
                )
                ->references(['company_id', 'id'])
                ->on('contacts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropForeign('companies_primary_contact_owner_fk');
            $table->dropForeign('companies_primary_contact_company_fk');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropUnique('contacts_user_id_id_unique');
            $table->dropUnique('contacts_company_id_id_unique');
        });
    }
};
