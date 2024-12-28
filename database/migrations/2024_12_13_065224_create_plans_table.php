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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('price')->default(0); // per day
            $table->boolean('customized')->default(true);
            $table->boolean('base')->default(false);
            $table->float('coefficient')->default(1);
            $table->integer('source_id');
            $table->timestamps();
        });

        Schema::table('instances', function (Blueprint $table) {
            $table->integer('plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });

        Schema::dropIfExists('plans');
    }
};
