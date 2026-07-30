<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            if (!Schema::hasColumn('enrollees', 'community')) {
                $table->string('community')->nullable()->after('village');
            }

            if (!Schema::hasColumn('enrollees', 'educational_status')) {
                $table->string('educational_status')->nullable()->after('occupation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('enrollees', 'community') ? 'community' : null,
                Schema::hasColumn('enrollees', 'educational_status') ? 'educational_status' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
