<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capitations', function (Blueprint $table) {
            if (!Schema::hasColumn('capitations', 'period_start')) {
                $table->date('period_start')->nullable()->after('name');
            }
            if (!Schema::hasColumn('capitations', 'period_end')) {
                $table->date('period_end')->nullable()->after('period_start');
            }
            if (!Schema::hasColumn('capitations', 'capitation_rate')) {
                $table->decimal('capitation_rate', 14, 2)->default(0)->after('period_end');
            }
            if (!Schema::hasColumn('capitations', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('capitations', 'computed_at')) {
                $table->timestamp('computed_at')->nullable();
            }
            if (!Schema::hasColumn('capitations', 'computed_by')) {
                $table->foreignId('computed_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('capitations', 'finalised_at')) {
                $table->timestamp('finalised_at')->nullable();
            }
            if (!Schema::hasColumn('capitations', 'finalised_by')) {
                $table->foreignId('finalised_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('capitation_details', function (Blueprint $table) {
            if (!Schema::hasColumn('capitation_details', 'capitated_month')) {
                $table->unsignedTinyInteger('capitated_month')->nullable()->after('facility_id');
            }
            if (!Schema::hasColumn('capitation_details', 'total_enrollees')) {
                $table->integer('total_enrollees')->default(0)->after('funding_type_id');
            }
            if (!Schema::hasColumn('capitation_details', 'capitation_rate')) {
                $table->decimal('capitation_rate', 14, 2)->default(0)->after('total_enrollees');
            }
            if (!Schema::hasColumn('capitation_details', 'total_amount')) {
                $table->decimal('total_amount', 14, 2)->default(0)->after('capitation_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('capitation_details', function (Blueprint $table) {
            $table->dropColumn(['capitated_month', 'total_enrollees', 'capitation_rate', 'total_amount']);
        });

        Schema::table('capitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalised_by');
            $table->dropColumn('finalised_at');
            $table->dropConstrainedForeignId('computed_by');
            $table->dropColumn('computed_at');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['period_start', 'period_end', 'capitation_rate']);
        });
    }
};
