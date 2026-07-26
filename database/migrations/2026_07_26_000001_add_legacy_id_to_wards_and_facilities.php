<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wards') && !Schema::hasColumn('wards', 'legacy_id')) {
            Schema::table('wards', function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_id')->nullable()->after('id')->index();
            });
        }

        if (Schema::hasTable('facilities') && !Schema::hasColumn('facilities', 'legacy_id')) {
            Schema::table('facilities', function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('facilities') && Schema::hasColumn('facilities', 'legacy_id')) {
            Schema::table('facilities', function (Blueprint $table): void {
                $table->dropIndex(['legacy_id']);
                $table->dropColumn('legacy_id');
            });
        }

        if (Schema::hasTable('wards') && Schema::hasColumn('wards', 'legacy_id')) {
            Schema::table('wards', function (Blueprint $table): void {
                $table->dropIndex(['legacy_id']);
                $table->dropColumn('legacy_id');
            });
        }
    }
};
