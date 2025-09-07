<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vulnerable_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->unsignedSmallInteger('status')->default(1);
            $table->timestamps();
        });

        $vulnerable_groups = [
            [ 'name' => '', 'code', 'none'],
            [ 'name' => 'Children Under 5 Years', 'code', 'cu5'],
            [ 'name' => 'Female Reproductive Age', 'code', 'fra'],
            [ 'name' => 'Elderly', 'code', 'elder'],
            [ 'name' => 'Othes', 'code', 'others'],
        ];

        foreach ($vulnerable_groups as $vulnerable_group) {
            DB::table('vulnerable_groups')->insert($vulnerable_group);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vulnerable_groups');
    }
};
