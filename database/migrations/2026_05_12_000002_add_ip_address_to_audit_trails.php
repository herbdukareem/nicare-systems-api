<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-09: Adds ip_address to audit_trails so every audit log entry
 * records the originating IP address as required by the business rule spec.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};
