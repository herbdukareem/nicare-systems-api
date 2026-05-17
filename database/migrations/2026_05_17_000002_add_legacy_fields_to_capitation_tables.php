<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('capitations')) {
            Schema::table('capitations', function (Blueprint $table): void {
                if (!Schema::hasColumn('capitations', 'metadata')) {
                    $table->json('metadata')->nullable()->after('status');
                }
            });
        }

        if (Schema::hasTable('capitation_details')) {
            Schema::table('capitation_details', function (Blueprint $table): void {
                if (!Schema::hasColumn('capitation_details', 'metadata')) {
                    $table->json('metadata')->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. Legacy migration metadata is safe to leave in place.
    }
};
