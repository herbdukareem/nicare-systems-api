<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_form_schemas', function (Blueprint $table): void {
            if (!Schema::hasColumn('enrollment_form_schemas', 'location_capture_policy')) {
                $table->json('location_capture_policy')->nullable()->after('nin_verification_policy');
            }
        });

        Schema::table('mobile_enrollment_records', function (Blueprint $table): void {
            if (!Schema::hasColumn('mobile_enrollment_records', 'location_capture_policy')) {
                $table->json('location_capture_policy')->nullable()->after('verified_field_edit_reasons');
            }
            if (!Schema::hasColumn('mobile_enrollment_records', 'location_payload')) {
                $table->json('location_payload')->nullable()->after('location_capture_policy');
            }
        });

        Schema::table('enrollees', function (Blueprint $table): void {
            if (!Schema::hasColumn('enrollees', 'enrollment_location_audit')) {
                $table->json('enrollment_location_audit')->nullable()->after('enrollment_extra_fields');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            if (Schema::hasColumn('enrollees', 'enrollment_location_audit')) {
                $table->dropColumn('enrollment_location_audit');
            }
        });

        Schema::table('mobile_enrollment_records', function (Blueprint $table): void {
            foreach (['location_payload', 'location_capture_policy'] as $column) {
                if (Schema::hasColumn('mobile_enrollment_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('enrollment_form_schemas', function (Blueprint $table): void {
            if (Schema::hasColumn('enrollment_form_schemas', 'location_capture_policy')) {
                $table->dropColumn('location_capture_policy');
            }
        });
    }
};
