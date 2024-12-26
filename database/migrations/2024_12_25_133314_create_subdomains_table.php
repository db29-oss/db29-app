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
        Schema::create('subdomains', function (Blueprint $table) {
            $table->uuid('id');
            $table->string('subdomain');
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->integer('subdomain_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('subdomain_count');
        });

        Schema::dropIfExists('subdomains');
    }
};
