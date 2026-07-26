<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('capitations') && !Schema::hasColumn('capitations', 'duplicate_nin_policy')) {
            Schema::table('capitations', function (Blueprint $table): void {
                $table->string('duplicate_nin_policy', 20)
                    ->default('exclude')
                    ->after('funding_type_id');
            });

            DB::table('capitations')
                ->where('metadata->source_table', 'capitation_grouping')
                ->update(['duplicate_nin_policy' => 'include']);
        }

        if (Schema::hasTable('enrollees') && !Schema::hasColumn('enrollees', 'has_duplicate_nin')) {
            Schema::table('enrollees', function (Blueprint $table): void {
                $table->boolean('has_duplicate_nin')
                    ->default(false)
                    ->after('is_possible_duplicate');
                $table->index(['has_duplicate_nin', 'nin'], 'enrollees_duplicate_nin_nin_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('enrollees') && Schema::hasColumn('enrollees', 'has_duplicate_nin')) {
            Schema::table('enrollees', function (Blueprint $table): void {
                $table->dropIndex('enrollees_duplicate_nin_nin_index');
                $table->dropColumn('has_duplicate_nin');
            });
        }

        if (Schema::hasTable('capitations') && Schema::hasColumn('capitations', 'duplicate_nin_policy')) {
            Schema::table('capitations', function (Blueprint $table): void {
                $table->dropColumn('duplicate_nin_policy');
            });
        }
    }
};
