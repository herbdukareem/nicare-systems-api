<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capitation_details', function (Blueprint $table) {
            if (!Schema::hasColumn('capitation_details', 'benefactor_id')) {
                $table->foreignId('benefactor_id')
                    ->nullable()
                    ->after('funding_type_id')
                    ->constrained('benefactors')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('capitation_details', function (Blueprint $table) {
            if (Schema::hasColumn('capitation_details', 'benefactor_id')) {
                $table->dropConstrainedForeignId('benefactor_id');
            }
        });
    }
};
