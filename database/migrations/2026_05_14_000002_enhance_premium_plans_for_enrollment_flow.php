<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premium_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('premium_plans', 'has_no_expiry')) {
                $table->boolean('has_no_expiry')->default(false)->after('duration_days');
            }

            if (!Schema::hasColumn('premium_plans', 'capitation_rate')) {
                $table->decimal('capitation_rate', 14, 2)->default(0)->after('amount');
            }

            if (!Schema::hasColumn('premium_plans', 'consultant_fee')) {
                $table->decimal('consultant_fee', 14, 2)->default(0)->after('capitation_rate');
            }

            if (!Schema::hasColumn('premium_plans', 'payment_required')) {
                $table->boolean('payment_required')->default(false)->after('consultant_fee');
            }

            if (!Schema::hasColumn('premium_plans', 'payment_gateway')) {
                $table->string('payment_gateway')->nullable()->after('payment_required');
            }

            if (Schema::hasTable('merchants') && !Schema::hasColumn('premium_plans', 'merchant_id')) {
                $table->foreignId('merchant_id')->nullable()->after('payment_gateway')->constrained('merchants')->nullOnDelete();
            }

            if (Schema::hasTable('merchant_service_types') && !Schema::hasColumn('premium_plans', 'merchant_service_type_id')) {
                $table->foreignId('merchant_service_type_id')->nullable()->after('merchant_id')->constrained('merchant_service_types')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('premium_plans', 'duration_days')) {
            DB::statement('ALTER TABLE premium_plans MODIFY duration_days INT UNSIGNED NULL');
        }

        if (Schema::hasColumn('premium_plans', 'maximum_dependants')) {
            DB::statement('ALTER TABLE premium_plans MODIFY maximum_dependants INT UNSIGNED NULL DEFAULT 0');
        }

        if (Schema::hasColumn('premium_plans', 'payment_required')) {
            DB::statement('ALTER TABLE premium_plans MODIFY payment_required TINYINT(1) NOT NULL DEFAULT 0');
        }

        DB::table('premium_plans')
            ->where(function ($query) {
                $query->where('is_family_plan', false)->orWhereNull('is_family_plan');
            })
            ->update(['maximum_dependants' => 0]);

        if (Schema::hasColumn('premium_plans', 'has_no_expiry')) {
            DB::table('premium_plans')->where('has_no_expiry', true)->update(['duration_days' => null]);
        }

        if (Schema::hasColumn('premium_plans', 'consultant_fee')) {
            DB::table('premium_plans')->whereNull('consultant_fee')->update(['consultant_fee' => 0]);
        }

        if (Schema::hasColumn('premium_plans', 'capitation_rate')) {
            DB::table('premium_plans')->whereNull('capitation_rate')->update(['capitation_rate' => 0]);
        }

        if (Schema::hasColumn('premium_plans', 'payment_required')) {
            $paymentReset = ['payment_gateway' => null];
            if (Schema::hasColumn('premium_plans', 'merchant_id')) {
                $paymentReset['merchant_id'] = null;
            }
            if (Schema::hasColumn('premium_plans', 'merchant_service_type_id')) {
                $paymentReset['merchant_service_type_id'] = null;
            }

            DB::table('premium_plans')
                ->where(function ($query) {
                    $query->where('payment_required', false)->orWhereNull('payment_required');
                })
                ->update($paymentReset);
        }
    }

    public function down(): void
    {
        Schema::table('premium_plans', function (Blueprint $table) {
            if (Schema::hasColumn('premium_plans', 'merchant_service_type_id')) {
                $table->dropConstrainedForeignId('merchant_service_type_id');
            }

            if (Schema::hasColumn('premium_plans', 'merchant_id')) {
                $table->dropConstrainedForeignId('merchant_id');
            }

            foreach (['consultant_fee', 'capitation_rate', 'has_no_expiry'] as $column) {
                if (Schema::hasColumn('premium_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
