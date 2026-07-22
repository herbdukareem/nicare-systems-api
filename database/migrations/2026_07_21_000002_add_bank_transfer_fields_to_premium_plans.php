<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premium_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('premium_plans', 'bank_transfer_enabled')) {
                $table->boolean('bank_transfer_enabled')->default(false)->after('payment_gateway');
            }

            if (!Schema::hasColumn('premium_plans', 'bank_transfer_bank_name')) {
                $table->string('bank_transfer_bank_name')->nullable()->after('bank_transfer_enabled');
            }

            if (!Schema::hasColumn('premium_plans', 'bank_transfer_account_name')) {
                $table->string('bank_transfer_account_name')->nullable()->after('bank_transfer_bank_name');
            }

            if (!Schema::hasColumn('premium_plans', 'bank_transfer_account_number')) {
                $table->string('bank_transfer_account_number', 50)->nullable()->after('bank_transfer_account_name');
            }

            if (!Schema::hasColumn('premium_plans', 'bank_transfer_instructions')) {
                $table->text('bank_transfer_instructions')->nullable()->after('bank_transfer_account_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('premium_plans', function (Blueprint $table) {
            foreach ([
                'bank_transfer_instructions',
                'bank_transfer_account_number',
                'bank_transfer_account_name',
                'bank_transfer_bank_name',
                'bank_transfer_enabled',
            ] as $column) {
                if (Schema::hasColumn('premium_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
