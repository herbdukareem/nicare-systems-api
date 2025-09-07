<?php

use FontLib\Table\Type\name;
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
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->unsignedSmallInteger('status')->default(1);
            $table->timestamps();
        });


        $sectors = [
            [
                'name' => 'Informal',
                'code' => 'informal'
            ],
             [
                'name' => 'Formal',
                'code' => 'formal'
            ],
             [
                'name' => 'TiShip',
                'code' => 'tiship'
            ]
        ];
        foreach ($sectors as $sector) {
            DB::table('sectors')->insert($sector);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
