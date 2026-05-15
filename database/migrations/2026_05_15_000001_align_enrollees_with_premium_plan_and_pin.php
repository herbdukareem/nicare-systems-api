<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table) {
            if (!Schema::hasColumn('enrollees', 'premium_plan_id')) {
                $column = $table->foreignId('premium_plan_id')->nullable();
                if (Schema::hasColumn('enrollees', 'premium_id')) {
                    $column->after('premium_id');
                }
                $column->constrained('premium_plans')->nullOnDelete();
            }

            if (!Schema::hasColumn('enrollees', 'premium_pin_id')) {
                $table->foreignId('premium_pin_id')->nullable()->after('premium_plan_id')->constrained('premium_pins')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('enrollees', 'premium_id') && Schema::hasColumn('enrollees', 'premium_plan_id')) {
            DB::table('enrollees')
                ->whereNull('premium_plan_id')
                ->whereNotNull('premium_id')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('premium_plans')
                        ->whereColumn('premium_plans.id', 'enrollees.premium_id');
                })
                ->update(['premium_plan_id' => DB::raw('premium_id')]);
        }

        Schema::table('premium_pins', function (Blueprint $table) {
            if (!Schema::hasColumn('premium_pins', 'legacy_id')) {
                $table->unsignedBigInteger('legacy_id')->nullable()->index()->after('id');
            }

            if (!Schema::hasColumn('premium_pins', 'legacy_request_id')) {
                $table->unsignedBigInteger('legacy_request_id')->nullable()->index()->after('legacy_id');
            }

            if (!Schema::hasColumn('premium_pins', 'legacy_status')) {
                $table->string('legacy_status')->nullable()->after('status');
            }

            if (!Schema::hasColumn('premium_pins', 'metadata')) {
                $table->json('metadata')->nullable()->after('legacy_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('premium_pins', function (Blueprint $table) {
            foreach (['metadata', 'legacy_status', 'legacy_request_id', 'legacy_id'] as $column) {
                if (Schema::hasColumn('premium_pins', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('enrollees', function (Blueprint $table) {
            if (Schema::hasColumn('enrollees', 'premium_pin_id')) {
                $table->dropConstrainedForeignId('premium_pin_id');
            }

            if (Schema::hasColumn('enrollees', 'premium_plan_id')) {
                $table->dropConstrainedForeignId('premium_plan_id');
            }
        });
    }
};
