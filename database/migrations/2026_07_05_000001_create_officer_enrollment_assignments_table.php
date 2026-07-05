<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'mobile_enrollment_disabled_at')) {
                $table->timestamp('mobile_enrollment_disabled_at')->nullable()->after('status');
            }
        });

        Schema::create('officer_enrollment_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained('lgas')->nullOnDelete();
            $table->foreignId('enrollment_form_schema_id')->nullable()->constrained('enrollment_form_schemas')->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'enabled'], 'officer_enroll_user_enabled_idx');
            $table->index(['lga_id', 'enabled'], 'officer_enroll_lga_enabled_idx');
            $table->index(['enrollment_form_schema_id', 'enabled'], 'officer_enroll_schema_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_enrollment_assignments');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'mobile_enrollment_disabled_at')) {
                $table->dropColumn('mobile_enrollment_disabled_at');
            }
        });
    }
};
