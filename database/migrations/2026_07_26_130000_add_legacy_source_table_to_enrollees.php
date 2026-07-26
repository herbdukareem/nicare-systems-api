<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('enrollees') || Schema::hasColumn('enrollees', 'legacy_source_table')) {
            return;
        }

        Schema::table('enrollees', function (Blueprint $table): void {
            $table->string('legacy_source_table')->nullable()->after('legacy_id');
            $table->index(['legacy_source_table', 'legacy_id'], 'enrollees_legacy_source_legacy_id_index');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('enrollees') || !Schema::hasColumn('enrollees', 'legacy_source_table')) {
            return;
        }

        Schema::table('enrollees', function (Blueprint $table): void {
            $table->dropIndex('enrollees_legacy_source_legacy_id_index');
            $table->dropColumn('legacy_source_table');
        });
    }
};
