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
        Schema::table('sources', function (Blueprint $table) {
            // $versions = [
            //     'tag' => 'version',
            //     'init_tmpl' => 'init template',
            //     'bkup_tmpl' => 'backup template',
            //     'rsto_tmpl' => 'restore template,
            // ]
            $table->jsonb('version_templates')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn('version_templates');
        });
    }
};
