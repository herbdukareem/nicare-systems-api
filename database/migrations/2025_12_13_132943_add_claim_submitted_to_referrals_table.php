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
            // Track if claim has been submitted for this referral
            $table->boolean('claim_submitted')->default(false)->after('status');
            $table->timestamp('claim_submitted_at')->nullable()->after('claim_submitted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn(['claim_submitted', 'claim_submitted_at']);
        });
    }
};
