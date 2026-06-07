<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('premium_plans') && !Schema::hasColumn('premium_plans', 'self_enrollment_enabled')) {
            Schema::table('premium_plans', function (Blueprint $table) {
                $table->boolean('self_enrollment_enabled')
                    ->default(false)
                    ->after('payment_required');
            });
        }

        if (Schema::hasTable('enrollees') && !Schema::hasColumn('enrollees', 'premium_purchase_id')) {
            Schema::table('enrollees', function (Blueprint $table) {
                $table->foreignId('premium_purchase_id')
                    ->nullable()
                    ->after('premium_pin_id')
                    ->constrained('premium_purchases')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('enrollees') && !Schema::hasColumn('enrollees', 'enrollment_source')) {
            Schema::table('enrollees', function (Blueprint $table) {
                $table->string('enrollment_source', 40)
                    ->default('staff')
                    ->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('enrollees', function (Blueprint $table) {
            if (Schema::hasColumn('enrollees', 'premium_purchase_id')) {
                $table->dropConstrainedForeignId('premium_purchase_id');
            }

            if (Schema::hasColumn('enrollees', 'enrollment_source')) {
                $table->dropColumn('enrollment_source');
            }
        });

        Schema::table('premium_plans', function (Blueprint $table) {
            if (Schema::hasColumn('premium_plans', 'self_enrollment_enabled')) {
                $table->dropColumn('self_enrollment_enabled');
            }
        });
    }
};
