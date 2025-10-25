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
        Schema::table('referrals', function (Blueprint $table) {
            // Add UTN validation fields for secondary/tertiary facilities
            $table->string('utn')->nullable()->after('referral_code');
            $table->boolean('utn_validated')->default(false)->after('utn');
            $table->timestamp('utn_validated_at')->nullable()->after('utn_validated');
            $table->foreignId('utn_validated_by')->nullable()->constrained('users')->after('utn_validated_at');
            $table->text('utn_validation_notes')->nullable()->after('utn_validated_by');
            
            // Add indexes for better performance
            $table->index(['utn']);
            $table->index(['utn_validated']);
            $table->index(['utn_validated_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['utn_validated_by']);
            
            // Drop indexes
            $table->dropIndex(['utn']);
            $table->dropIndex(['utn_validated']);
            $table->dropIndex(['utn_validated_by']);
            
            // Drop columns
            $table->dropColumn([
                'utn',
                'utn_validated',
                'utn_validated_at',
                'utn_validated_by',
                'utn_validation_notes'
            ]);
        });
    }
};
