<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table) {
            $table->boolean('is_possible_duplicate')->default(false)->after('id');
            $table->boolean('duplicate_reviewed')->default(false)->after('is_possible_duplicate');
            $table->foreignId('duplicate_reviewed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->after('duplicate_reviewed');
            $table->timestamp('duplicate_reviewed_at')->nullable()->after('duplicate_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('enrollees', function (Blueprint $table) {
            $table->dropForeign(['duplicate_reviewed_by']);
            $table->dropColumn(['is_possible_duplicate', 'duplicate_reviewed', 'duplicate_reviewed_by', 'duplicate_reviewed_at']);
        });
    }
};
