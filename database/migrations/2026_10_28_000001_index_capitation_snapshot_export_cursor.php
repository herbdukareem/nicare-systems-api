<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capitation_detail_enrollees', function (Blueprint $table): void {
            $table->index(['capitation_id', 'id'], 'capitation_snapshot_export_cursor_index');
        });
    }

    public function down(): void
    {
        Schema::table('capitation_detail_enrollees', function (Blueprint $table): void {
            $table->dropIndex('capitation_snapshot_export_cursor_index');
        });
    }
};
