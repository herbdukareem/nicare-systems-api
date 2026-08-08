<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premium_purchases', function (Blueprint $table) {
            $table->decimal('base_amount', 14, 2)->nullable()->after('amount');
            $table->decimal('processing_fee', 14, 2)->default(0)->after('base_amount');
            $table->decimal('customer_total', 14, 2)->nullable()->after('processing_fee');
        });
    }

    public function down(): void
    {
        Schema::table('premium_purchases', function (Blueprint $table) {
            $table->dropColumn(['base_amount', 'processing_fee', 'customer_total']);
        });
    }
};
