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
        Schema::table('claim_lines', function (Blueprint $table) {
            $table->unsignedInteger('approved_quantity')->nullable()->after('quantity')
                  ->comment('Quantity approved by reviewer (may differ from claimed quantity)');
            $table->decimal('approved_amount', 10, 2)->nullable()->after('line_total')
                  ->comment('Final approved amount for this line item');
            $table->boolean('is_approved')->default(true)->after('approved_amount')
                  ->comment('Whether this line item is included in final approval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claim_lines', function (Blueprint $table) {
            $table->dropColumn(['approved_quantity', 'approved_amount', 'is_approved']);
        });
    }
};
