<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premium_purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('premium_purchases', 'gateway_code')) {
                $table->string('gateway_code', 80)->nullable()->after('payment_method');
            }

            if (!Schema::hasColumn('premium_purchases', 'gateway_status')) {
                $table->string('gateway_status', 80)->nullable()->after('gateway_code');
            }

            if (!Schema::hasColumn('premium_purchases', 'authorization_url')) {
                $table->text('authorization_url')->nullable()->after('payment_reference');
            }

            if (!Schema::hasColumn('premium_purchases', 'gateway_access_code')) {
                $table->string('gateway_access_code', 255)->nullable()->after('authorization_url');
            }

            if (!Schema::hasColumn('premium_purchases', 'gateway_response')) {
                $table->json('gateway_response')->nullable()->after('gateway_access_code');
            }

            if (!Schema::hasColumn('premium_purchases', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('premium_purchases', function (Blueprint $table) {
            foreach ([
                'verified_at',
                'gateway_response',
                'gateway_access_code',
                'authorization_url',
                'gateway_status',
                'gateway_code',
            ] as $column) {
                if (Schema::hasColumn('premium_purchases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
