<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('claims', 'total_amount_claimed')) {
                $table->decimal('total_amount_claimed', 12, 2)->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('claims', 'total_amount_approved')) {
                $table->decimal('total_amount_approved', 12, 2)->default(0)->after('total_amount_claimed');
            }
            if (!Schema::hasColumn('claims', 'total_amount_paid')) {
                $table->decimal('total_amount_paid', 12, 2)->default(0)->after('total_amount_approved');
            }
            if (!Schema::hasColumn('claims', 'bundle_amount')) {
                $table->decimal('bundle_amount', 12, 2)->default(0)->after('total_amount_paid');
            }
            if (!Schema::hasColumn('claims', 'ffs_amount')) {
                $table->decimal('ffs_amount', 12, 2)->default(0)->after('bundle_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            if (Schema::hasColumn('claims', 'total_amount_claimed')) {
                $table->dropColumn('total_amount_claimed');
            }
            if (Schema::hasColumn('claims', 'total_amount_approved')) {
                $table->dropColumn('total_amount_approved');
            }
            if (Schema::hasColumn('claims', 'total_amount_paid')) {
                $table->dropColumn('total_amount_paid');
            }
            if (Schema::hasColumn('claims', 'bundle_amount')) {
                $table->dropColumn('bundle_amount');
            }
            if (Schema::hasColumn('claims', 'ffs_amount')) {
                $table->dropColumn('ffs_amount');
            }
        });
    }
};
