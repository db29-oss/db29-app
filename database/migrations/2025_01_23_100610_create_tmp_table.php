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
        Schema::create('tmp', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('k');
            $table->json('v');
            $table->unique(['user_id', 'k']);
            $table->timestamps();
        });

        DB::statement(
            "ALTER TABLE tmp SET ".
            "(ttl_expiration_expression = $$(updated_at::TIMESTAMPTZ + '1 days')$$)"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tmp');
    }
};
