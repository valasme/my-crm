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
                ['user_id', 'company_id', 'status', 'activity_at'],
                'activities_user_company_status_activity_at_index',
            );

            $table->index(
                ['user_id', 'contact_id', 'status', 'activity_at'],
                'activities_user_contact_status_activity_at_index',
            );
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'company_id', 'status', 'task_at'],
                'tasks_user_company_status_task_at_index',
            );

            $table->index(
                ['user_id', 'contact_id', 'status', 'task_at'],
                'tasks_user_contact_status_task_at_index',
            );

            $table->index(
                [
                    'user_id',
                    'company_id',
                    'status',
                    'is_active',
                    'next_follow_up_at',
                ],
                'tasks_user_company_status_active_follow_up_index',
            );

            $table->index(
                [
                    'user_id',
                    'contact_id',
                    'status',
                    'is_active',
                    'next_follow_up_at',
                ],
                'tasks_user_contact_status_active_follow_up_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropIndex(
                'activities_user_company_status_activity_at_index',
            );
            $table->dropIndex(
                'activities_user_contact_status_activity_at_index',
            );
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_user_company_status_task_at_index');
            $table->dropIndex('tasks_user_contact_status_task_at_index');
            $table->dropIndex(
                'tasks_user_company_status_active_follow_up_index',
            );
            $table->dropIndex(
                'tasks_user_contact_status_active_follow_up_index',
            );
        });
    }
};
