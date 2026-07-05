<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_form_schemas', function (Blueprint $table): void {
            if (!Schema::hasColumn('enrollment_form_schemas', 'nin_verification_policy')) {
                $table->json('nin_verification_policy')->nullable()->after('requires_nin_verification');
            }
        });

        Schema::table('mobile_enrollment_records', function (Blueprint $table): void {
            if (!Schema::hasColumn('mobile_enrollment_records', 'nin_verification_policy')) {
                $table->json('nin_verification_policy')->nullable()->after('migration_hints');
            }
            if (!Schema::hasColumn('mobile_enrollment_records', 'nin_verified_data')) {
                $table->json('nin_verified_data')->nullable()->after('nin_verification_policy');
            }
            if (!Schema::hasColumn('mobile_enrollment_records', 'nin_autofill_changes')) {
                $table->json('nin_autofill_changes')->nullable()->after('nin_verified_data');
            }
            if (!Schema::hasColumn('mobile_enrollment_records', 'nin_conflicts')) {
                $table->json('nin_conflicts')->nullable()->after('nin_autofill_changes');
            }
            if (!Schema::hasColumn('mobile_enrollment_records', 'verified_field_edit_reasons')) {
                $table->json('verified_field_edit_reasons')->nullable()->after('nin_conflicts');
            }
        });

        if (Schema::hasTable('mobile_enrollment_records') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mobile_enrollment_records MODIFY status ENUM('received','pending_nin','nin_failed','duplicate_suspected','pending_approval','requires_review','approved','rejected','sync_failed') DEFAULT 'received'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mobile_enrollment_records') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mobile_enrollment_records MODIFY status ENUM('received','pending_nin','nin_failed','duplicate_suspected','pending_approval','approved','rejected','sync_failed') DEFAULT 'received'");
        }

        Schema::table('mobile_enrollment_records', function (Blueprint $table): void {
            foreach ([
                'verified_field_edit_reasons',
                'nin_conflicts',
                'nin_autofill_changes',
                'nin_verified_data',
                'nin_verification_policy',
            ] as $column) {
                if (Schema::hasColumn('mobile_enrollment_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('enrollment_form_schemas', function (Blueprint $table): void {
            if (Schema::hasColumn('enrollment_form_schemas', 'nin_verification_policy')) {
                $table->dropColumn('nin_verification_policy');
            }
        });
    }
};
