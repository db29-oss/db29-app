<?php

namespace App\Jobs;

use App\Models\TrafficRouter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PrepareTrafficRouter implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $traffic_router_id
    ) {}

    public function handle(): void
    {
        $traffic_router = TrafficRouter::whereId($this->traffic_router_id)->with('machine')->first();

        $ssh = app('ssh')->toMachine($traffic_router->machine);

        if ($traffic_router->machine->ssh_username === 'root') {
            // we may not have sudo util by default
            $ssh->exec('DEBIAN_FRONTEND=noninteractive apt update && apt install sudo');
        } else {
            $ssh->exec('DEBIAN_FRONTEND=noninteractive sudo apt update');
        }

        $rt = app('rt', [$traffic_router, $ssh]);

        $caddyfile_path = '/etc/caddy/db29.caddyfile';

        $ssh->exec('DEBIAN_FRONTEND=noninteractive sudo apt install caddy curl -y');

        // on testing container env some systemd config cannot be run
        // all config applied below was test using a real machine
        // we should improve testing in the future

        $ssh->exec(
            'sudo touch '.$caddyfile_path.' && '.
            'sudo mkdir -p /etc/caddy/sites/ && '.
            'sudo echo '.
            escapeshellarg('import /etc/caddy/sites/*.caddyfile').' > '.$caddyfile_path
        );

        $caddyfile_content = <<<CADDYFILE
:80 {
    root * {$tr->machine->storage_path}www/

    handle /.well-known/acme-challenge/* {
        file_server
    }

    handle /ping {
        respond "{$tr->machine->id}" 200
    }

    handle {
        @http_requests {
            not path "/.well-known/acme-challenge/*"
        }
        redir https://{http.request.host}{http.request.uri} 301
    }
}
CADDYFILE;

        $caddyfile_content_lines = explode(PHP_EOL, $caddyfile_content);

        foreach ($caddyfile_content_lines as $line) {
            $ssh->exec('sudo echo '.escapeshellarg($line).' >> '.$caddyfile_path);
        }

        // extra routes
        if ($tr->extra_routes !== '') {
            $extra_routes_lines = explode(PHP_EOL, $tr->extra_routes);

            foreach ($extra_routes_lines as $line) {
                $ssh->exec('sudo echo '.escapeshellarg($line).' >> '.$caddyfile_path);
            }
        }

        $ssh->exec('sudo cat /lib/systemd/system/caddy.service');

        foreach ($ssh->getOutput() as $line) {
            if (str_starts_with($line, 'ExecStart=')) {
                break;
            }
        }

        $commands = [];

        $override_content_lines =
            [
                "[Service]",
                "ExecStart=", // reset mechanism of systemd
                "ExecStart=/usr/bin/caddy run --config {$caddyfile_path} --adapter caddyfile",
                "ExecReload=", // reset mechanism of systemd
                "ExecReload=/usr/bin/caddy reload --config {$caddyfile_path} --adapter caddyfile --force",
            ];

        foreach ($override_content_lines as $override_content_line) {
            $commands[] = 'sudo echo '.
                escapeshellarg($override_content_line).' >> '.
                '/etc/systemd/system/caddy.service.d/override.conf';
        }

        $ssh->exec(array_merge(
            [
                'sudo mkdir -p /etc/systemd/system/caddy.service.d/',
                'sudo rm -rf /etc/systemd/system/caddy.service.d/override.conf',
                'sudo touch /etc/systemd/system/caddy.service.d/override.conf',
                'sudo touch '.$caddyfile_path,
                'sudo mkdir -p /var/lib/caddy/.config/caddy',
                'sudo rm -f /var/lib/caddy/.config/caddy/autosave.json',
                'sudo touch /var/lib/caddy/.config/caddy/autosave.json',
            ],
            $commands,
        ));

        if (app('env') === 'production') {
            $ssh->exec(
                [
                    'sudo systemctl enable caddy',
                    'sudo systemctl daemon-reload',
                    'sudo systemctl stop caddy',
                    'sudo systemctl start caddy',
                ]
            );
        }

        if (app('env') === 'testing') {
            $ssh->clearOutput();

            $ssh->exec('sudo ps aux | grep caddy');

            if (count($ssh->getOutput()) < 2) { // grep process and bash process
                $ssh->exec('sudo /usr/bin/caddy start --config '.$caddyfile_path.' --adapter caddyfile');
            }
        }

        $rt->reload();

        TrafficRouter::whereId($tr->id)->update(['prepared' => true]);
    }
}
