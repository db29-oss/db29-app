<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamp('turned_on_at')->nullable();
            $table->timestamp('turned_off_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            $table->dropColumn('paid_at');
            $table->dropColumn('turned_on_at');
            $table->dropColumn('turned_off_at');
        });
    }
};
