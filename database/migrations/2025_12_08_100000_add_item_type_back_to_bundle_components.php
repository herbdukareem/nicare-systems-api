<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundle_components', function (Blueprint $table) {
            $table->string('item_type')->after('max_quantity')->comment('LAB, DRUG, CONSULTATION, etc.');
        });
    }

    public function down(): void
    {
        Schema::table('bundle_components', function (Blueprint $table) {
            $table->dropColumn('item_type');
        });
    }
};

