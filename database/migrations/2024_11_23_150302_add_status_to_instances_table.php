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
        Schema::table('instances', function (Blueprint $table) {
            // queue -> init -> dns_up -> ct_up -> rt_up ->
            // rt_dw -> dns_dw -> ct_dw
            $table->string('status')->default('queue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
