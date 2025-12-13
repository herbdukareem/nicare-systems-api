<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->timestamp('valid_until')
                  ->nullable()
                  ->after('utn')
                  ->comment('Last date referral can be validated (UTN)');
        });

        // Backfill existing referrals to default 3 months from creation
        DB::table('referrals')
            ->whereNull('valid_until')
            ->update([
                'valid_until' => DB::raw('DATE_ADD(created_at, INTERVAL 3 MONTH)')
            ]);
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('valid_until');
        });
    }
};
