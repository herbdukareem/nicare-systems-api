<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('premium_plans') || Schema::hasColumn('premium_plans', 'funding_type_id')) {
            return;
        }

        Schema::table('premium_plans', function (Blueprint $table): void {
            $table->foreignId('funding_type_id')
                ->nullable()
                ->after('benefit_package_id')
                ->constrained('funding_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('premium_plans') || !Schema::hasColumn('premium_plans', 'funding_type_id')) {
            return;
        }

        Schema::table('premium_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('funding_type_id');
        });
    }
};
