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
        if (! $this->supportsFullTextSearch()) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            $table->fullText(
                ['name', 'type', 'status', 'source', 'notes'],
                'activities_search_fulltext_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->supportsFullTextSearch()) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropFullText('activities_search_fulltext_index');
        });
    }

    private function supportsFullTextSearch(): bool
    {
        return in_array(
            DB::connection()->getDriverName(),
            ['mysql', 'mariadb'],
            true,
        );
    }
};
