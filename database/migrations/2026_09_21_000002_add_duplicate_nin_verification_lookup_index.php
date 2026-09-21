<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $indexName = 'enrollees_nin_verification_status_nin_idx';

    public function up(): void
    {
        if (!Schema::hasTable('enrollees') || $this->indexExists($this->indexName)) {
            return;
        }

        Schema::table('enrollees', function (Blueprint $table): void {
            $table->index(['nin_verification_status', 'nin'], $this->indexName);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('enrollees') || !$this->indexExists($this->indexName)) {
            return;
        }

        Schema::table('enrollees', function (Blueprint $table): void {
            $table->dropIndex($this->indexName);
        });
    }

    private function indexExists(string $indexName): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'select 1 as found from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, 'enrollees', $indexName]
        );

        return $result !== null;
    }
};
