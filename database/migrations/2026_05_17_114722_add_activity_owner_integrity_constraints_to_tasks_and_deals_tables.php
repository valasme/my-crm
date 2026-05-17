<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tasks')
            ->whereNotNull('activity_id')
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('activities')
                    ->whereColumn('activities.id', 'tasks.activity_id')
                    ->whereColumn('activities.user_id', 'tasks.user_id');
            })
            ->update(['activity_id' => null]);

        DB::table('deals')
            ->whereNotNull('activity_id')
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('activities')
                    ->whereColumn('activities.id', 'deals.activity_id')
                    ->whereColumn('activities.user_id', 'deals.user_id');
            })
            ->update(['activity_id' => null]);

        Schema::table('activities', function (Blueprint $table): void {
            $table->unique(['user_id', 'id'], 'activities_user_id_id_unique');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table
                ->foreign(
                    ['user_id', 'activity_id'],
                    'tasks_activity_owner_integrity_fk',
                )
                ->references(['user_id', 'id'])
                ->on('activities');
        });

        Schema::table('deals', function (Blueprint $table): void {
            $table
                ->foreign(
                    ['user_id', 'activity_id'],
                    'deals_activity_owner_integrity_fk',
                )
                ->references(['user_id', 'id'])
                ->on('activities');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign('tasks_activity_owner_integrity_fk');
        });

        Schema::table('deals', function (Blueprint $table): void {
            $table->dropForeign('deals_activity_owner_integrity_fk');
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropUnique('activities_user_id_id_unique');
        });
    }
};
