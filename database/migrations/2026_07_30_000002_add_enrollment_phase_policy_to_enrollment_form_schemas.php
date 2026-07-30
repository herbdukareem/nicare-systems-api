<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('enrollment_form_schemas') || Schema::hasColumn('enrollment_form_schemas', 'enrollment_phase_policy')) {
            return;
        }

        Schema::table('enrollment_form_schemas', function (Blueprint $table): void {
            $table->json('enrollment_phase_policy')
                ->nullable()
                ->after('location_capture_policy');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('enrollment_form_schemas') || !Schema::hasColumn('enrollment_form_schemas', 'enrollment_phase_policy')) {
            return;
        }

        Schema::table('enrollment_form_schemas', function (Blueprint $table): void {
            $table->dropColumn('enrollment_phase_policy');
        });
    }
};
