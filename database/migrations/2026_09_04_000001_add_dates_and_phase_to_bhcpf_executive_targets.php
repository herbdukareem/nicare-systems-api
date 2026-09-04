<?php

use App\Models\EnrollmentPhase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_phases', function (Blueprint $table): void {
            if (!Schema::hasColumn('enrollment_phases', 'start_date')) {
                $table->date('start_date')->nullable()->after('is_current');
            }
            if (!Schema::hasColumn('enrollment_phases', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });

        Schema::table('bhcpf_executive_targets', function (Blueprint $table): void {
            // Targets are now unique within a phase, not globally per LGA.
            $table->dropUnique('bhcpf_executive_targets_lga_id_unique');

            if (!Schema::hasColumn('bhcpf_executive_targets', 'enrollment_phase_id')) {
                $table->foreignId('enrollment_phase_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('enrollment_phases')
                    ->nullOnDelete();
            }
        });

        $this->assignExistingTargetsToBhcpfPhase();

        if (Schema::hasColumn('bhcpf_executive_targets', 'enrollment_phase_id')) {
            Schema::table('bhcpf_executive_targets', function (Blueprint $table): void {
                $table->unique(['enrollment_phase_id', 'lga_id'], 'bhcpf_targets_phase_lga_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bhcpf_executive_targets', function (Blueprint $table): void {
            $table->dropUnique('bhcpf_targets_phase_lga_unique');
            $table->dropConstrainedForeignId('enrollment_phase_id');
            $table->unique('lga_id');
        });

        Schema::table('enrollment_phases', function (Blueprint $table): void {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }

    private function assignExistingTargetsToBhcpfPhase(): void
    {
        $benefactorId = DB::table('benefactors')
            ->whereRaw('UPPER(name) = ?', ['BHCPF'])
            ->value('id');

        if (!$benefactorId) {
            return;
        }

        $phase = EnrollmentPhase::query()->firstOrCreate(
            ['name' => '65K BHCPF Enrollment'],
            [
                'benefactor_id' => $benefactorId,
                'status' => 1,
                'is_current' => true,
                'start_date' => '2026-08-03',
                'end_date' => '2026-12-31',
            ]
        );

        DB::table('bhcpf_executive_targets')
            ->whereNull('enrollment_phase_id')
            ->update(['enrollment_phase_id' => $phase->id]);
    }
};
