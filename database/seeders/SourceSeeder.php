<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $source = new Source;
        $source->name = 'planka';
        $source->enabled = true;
        $source->version_templates = '[{"commit":"617246ec407353cd69c875baff5524b5e0c852dd","docker_compose":{"services":{"planka":{"depends_on":{"postgres":{"condition":"service_healthy"}},"environment":["BASE_URL=http:\/\/localhost:3000","DATABASE_URL=postgresql:\/\/postgres@postgres\/planka","SECRET_KEY=notsecretkey"],"image":"ghcr.io\/plankanban\/planka:latest","ports":["3000:1337"],"restart":"on-failure","volumes":["user-avatars:\/app\/public\/user-avatars","project-background-images:\/app\/public\/project-background-images","attachments:\/app\/private\/attachments"]},"postgres":{"environment":["POSTGRES_DB=planka","POSTGRES_HOST_AUTH_METHOD=trust"],"healthcheck":{"interval":"10s","retries":5,"test":["CMD-SHELL","pg_isready -U postgres -d planka"],"timeout":"5s"},"image":"postgres:16-alpine","restart":"on-failure","volumes":["db-data:\/var\/lib\/postgresql\/data"]}},"version":"3","volumes":{"attachments":null,"db-data":null,"project-background-images":null,"user-avatars":null}},"tag":"v1.24.2"}]';
        $source->save();

        $source = new Source;
        $source->name = 'word_press';
        $source->enabled = true;
        $source->version_templates = '[{"commit":"6044190b526821ead3b4ff9ead9381ef879865d8","docker_compose":{"version":"3","services":{"wordpress":{"image":"docker.io\/kocoten1992\/wordpress-sqlite:latest","restart":"always","ports":["8080:80"],"volumes":["wordpress:\/var\/www\/html"]}},"volumes":{"wordpress":null}},"tag":"6.7.1"}]';
        $source->save();
    }
}
