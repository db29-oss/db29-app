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
            //     'commit' => 'commit',
            //     'init_tmpl' => 'init template',
            //     'bkup_tmpl' => 'backup template',
            //     'rsto_tmpl' => 'restore template,
            //     'docker_compose' => 'docker compose',
            // ]
            $table->jsonb('version_templates')->default('[]');
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
