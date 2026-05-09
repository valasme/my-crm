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
        Schema::table('activities', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'updated_at', 'id'],
                'activities_user_updated_id_index',
            );

            $table->index(
                ['user_id', 'created_at', 'id'],
                'activities_user_created_id_index',
            );

            $table->index(
                ['user_id', 'activity_at', 'id'],
                'activities_user_activity_date_id_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropIndex('activities_user_updated_id_index');
            $table->dropIndex('activities_user_created_id_index');
            $table->dropIndex('activities_user_activity_date_id_index');
        });
    }
};
