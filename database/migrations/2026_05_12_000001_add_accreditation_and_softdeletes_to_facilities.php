<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-08: Adds soft deletes to facilities table.
 * Also adds:
 *   - accreditation_status (active / suspended / revoked)
 *   - Faith-Based as a valid ownership type
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            // BR-08: soft delete support
            $table->softDeletes()->after('status');

            // Accreditation lifecycle (M-03)
            $table->enum('accreditation_status', ['active', 'suspended', 'revoked'])
                  ->default('active')
                  ->after('status');
        });

        // Extend the ownership enum to include Faith-Based.
        // MySQL does not support ALTER COLUMN on enums directly via Blueprint change(),
        // so we use a raw statement to extend it safely.
        DB::statement(
            "ALTER TABLE facilities MODIFY COLUMN ownership ENUM('Public','Private','Faith-Based') NOT NULL DEFAULT 'Public'"
        );
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('accreditation_status');
        });

        DB::statement(
            "ALTER TABLE facilities MODIFY COLUMN ownership ENUM('Public','Private') NOT NULL DEFAULT 'Public'"
        );
    }
};
