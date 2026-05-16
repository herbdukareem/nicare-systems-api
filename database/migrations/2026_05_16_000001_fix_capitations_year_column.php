<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('capitations') || !Schema::hasColumn('capitations', 'year')) {
            return;
        }

        DB::statement('ALTER TABLE capitations MODIFY `year` SMALLINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('capitations') || !Schema::hasColumn('capitations', 'year')) {
            return;
        }

        DB::statement('ALTER TABLE capitations MODIFY `year` TINYINT UNSIGNED NOT NULL');
    }
};
