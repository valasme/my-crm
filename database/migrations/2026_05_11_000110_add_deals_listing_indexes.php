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
        Schema::table('deals', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'updated_at', 'id'],
                'deals_user_updated_id_index',
            );

            $table->index(
                ['user_id', 'created_at', 'id'],
                'deals_user_created_id_index',
            );

            $table->index(
                ['user_id', 'deal_at', 'id'],
                'deals_user_deal_date_id_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->dropIndex('deals_user_updated_id_index');
            $table->dropIndex('deals_user_created_id_index');
            $table->dropIndex('deals_user_deal_date_id_index');
        });
    }
};
