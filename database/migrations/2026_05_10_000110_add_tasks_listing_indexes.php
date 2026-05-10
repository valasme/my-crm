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
        Schema::table('tasks', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'updated_at', 'id'],
                'tasks_user_updated_id_index',
            );

            $table->index(
                ['user_id', 'created_at', 'id'],
                'tasks_user_created_id_index',
            );

            $table->index(
                ['user_id', 'task_at', 'id'],
                'tasks_user_task_date_id_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_user_updated_id_index');
            $table->dropIndex('tasks_user_created_id_index');
            $table->dropIndex('tasks_user_task_date_id_index');
        });
    }
};
