<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('enrollment_phases')) {
            return;
        }

        $addLegacyId = !Schema::hasColumn('enrollment_phases', 'legacy_id');
        $addPhase = !Schema::hasColumn('enrollment_phases', 'phase');
        $addSponsor = !Schema::hasColumn('enrollment_phases', 'sponsor');
        $addFunding = !Schema::hasColumn('enrollment_phases', 'funding');
        $addIsCurrent = !Schema::hasColumn('enrollment_phases', 'is_current');

        if (!$addLegacyId && !$addPhase && !$addSponsor && !$addFunding && !$addIsCurrent) {
            return;
        }

        Schema::table('enrollment_phases', function (Blueprint $table) use ($addLegacyId, $addPhase, $addSponsor, $addFunding, $addIsCurrent): void {
            if ($addLegacyId) {
                $table->unsignedBigInteger('legacy_id')->nullable()->unique()->after('id');
            }
            if ($addPhase) {
                $table->string('phase', 100)->nullable()->after('name');
            }
            if ($addSponsor) {
                $table->string('sponsor', 100)->nullable()->after('phase');
            }
            if ($addFunding) {
                $table->string('funding', 20)->nullable()->after('sponsor');
            }
            if ($addIsCurrent) {
                $table->boolean('is_current')->default(false)->after('funding');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('enrollment_phases')) {
            return;
        }

        $columns = array_values(array_filter(
            ['is_current', 'funding', 'sponsor', 'phase', 'legacy_id'],
            fn (string $column): bool => Schema::hasColumn('enrollment_phases', $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('enrollment_phases', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
