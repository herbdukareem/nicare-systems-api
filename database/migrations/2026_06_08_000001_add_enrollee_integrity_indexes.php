<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            // Note: `nin` already has an index (enrollees_nin_idx) — not duplicated here.
            $table->index('created_at', 'enrollees_created_at_index');
            $table->index(['is_possible_duplicate', 'created_at'], 'enrollees_duplicate_created_index');
            $table->index(['ward_id', 'funding_type_id', 'benefactor_id'], 'enrollees_integrity_filter_index');
        });

        Schema::table('enrollee_duplicate_flags', function (Blueprint $table): void {
            $table->index(['resolved', 'created_at'], 'duplicate_flags_resolved_created_index');
            $table->index(['enrollee_id', 'resolved'], 'duplicate_flags_enrollee_resolved_index');
            $table->index(['matched_enrollee_id', 'resolved'], 'duplicate_flags_matched_resolved_index');
        });
    }

    public function down(): void
    {
        Schema::table('enrollee_duplicate_flags', function (Blueprint $table): void {
            $table->dropIndex('duplicate_flags_resolved_created_index');
            $table->dropIndex('duplicate_flags_enrollee_resolved_index');
            $table->dropIndex('duplicate_flags_matched_resolved_index');
        });

        Schema::table('enrollees', function (Blueprint $table): void {
            $table->dropIndex('enrollees_created_at_index');
            $table->dropIndex('enrollees_duplicate_created_index');
            $table->dropIndex('enrollees_integrity_filter_index');
        });
    }
};
