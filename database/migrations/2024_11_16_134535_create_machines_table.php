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
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->integer('ssh_port');
            $table->string('storage_path');
            $table->integer('max_core')->default(4);
            $table->integer('used_core')->default(0);
            $table->integer('max_memory')->default(100 * 1000 * 1000); // 100 MB
            $table->integer('used_memory')->default(0); // MB
            $table->integer('max_disk')->default(100 * 1000 * 1000 * 1000); // 100 GB
            $table->integer('used_disk')->default(0);
            $table->boolean('prepared')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
