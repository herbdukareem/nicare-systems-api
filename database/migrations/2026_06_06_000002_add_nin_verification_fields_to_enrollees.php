<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            if (!Schema::hasColumn('enrollees', 'nin_verification_status')) {
                $table->string('nin_verification_status')
                    ->default('not_started')
                    ->after('nin')
                    ->index();
            }

            if (!Schema::hasColumn('enrollees', 'nin_verified_at')) {
                $table->timestamp('nin_verified_at')->nullable()->after('approval_date');
            }

            if (!Schema::hasColumn('enrollees', 'nin_verified_by')) {
                $table->foreignId('nin_verified_by')
                    ->nullable()
                    ->after('approved_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('enrollees', 'nin_verification_provider')) {
                $table->string('nin_verification_provider')->nullable()->after('nin_verification_status');
            }

            if (!Schema::hasColumn('enrollees', 'nin_verification_data')) {
                $table->json('nin_verification_data')->nullable()->after('nin_verification_provider');
            }

            if (!Schema::hasColumn('enrollees', 'nin_verification_meta')) {
                $table->json('nin_verification_meta')->nullable()->after('nin_verification_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            if (Schema::hasColumn('enrollees', 'nin_verified_by')) {
                $table->dropForeign(['nin_verified_by']);
                $table->dropColumn('nin_verified_by');
            }

            foreach ([
                'nin_verification_status',
                'nin_verified_at',
                'nin_verification_provider',
                'nin_verification_data',
                'nin_verification_meta',
            ] as $column) {
                if (Schema::hasColumn('enrollees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
