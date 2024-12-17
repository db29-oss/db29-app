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
        Schema::table('machines', function (Blueprint $table) {
            $table->renameColumn('used_cpu', 'remain_cpu');
            $table->renameColumn('used_disk', 'remain_disk');
            $table->renameColumn('used_memory', 'remain_memory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->renameColumn('remain_cpu', 'used_cpu');
            $table->renameColumn('remain_disk', 'used_disk');
            $table->renameColumn('remain_memory', 'used_memory');
        });
    }
};
