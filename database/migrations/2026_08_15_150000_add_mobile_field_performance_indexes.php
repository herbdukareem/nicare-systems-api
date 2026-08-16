<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_enrollment_records', function (Blueprint $table): void {
            $this->indexIfMissing($table, 'mobile_enrollment_records', ['officer_device_id', 'status', 'updated_at'], 'mobile_enrollment_device_status_updated_idx');
        });

        Schema::table('mobile_enrollment_attachments', function (Blueprint $table): void {
            $this->indexIfMissing($table, 'mobile_enrollment_attachments', ['mobile_enrollment_record_id', 'kind'], 'mobile_attach_record_kind_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_enrollment_attachments', function (Blueprint $table): void {
            $this->dropIndexIfExists($table, 'mobile_enrollment_attachments', 'mobile_attach_record_kind_idx');
        });

        Schema::table('mobile_enrollment_records', function (Blueprint $table): void {
            $this->dropIndexIfExists($table, 'mobile_enrollment_records', 'mobile_enrollment_device_status_updated_idx');
        });
    }

    private function indexIfMissing(Blueprint $table, string $tableName, array $columns, string $indexName): void
    {
        if (!$this->indexExists($tableName, $indexName)) {
            $table->index($columns, $indexName);
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            $table->dropIndex($indexName);
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$tableName}')");

            return collect($indexes)->contains(fn (object $index): bool => ($index->name ?? null) === $indexName);
        }

        $indexes = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);

        return $indexes !== [];
    }
};
