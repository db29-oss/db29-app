<?php

namespace App\Console\Commands;

use App\Models\TrafficRouter;
use Illuminate\Console\Command;

class TrafficRouterPrepare extends Command
{
    protected $signature = 'app:traffic-router-prepare {--tr_id=} {--force}';

    protected $description = 'Install caddy and set up skeleton rule, will not rewrite already exists rules';

    public function handle()
    {
        // we are using caddy for traffic router
        // and manually instantiate caddy
        // to use SO_REUSEPORT for zero-downtime deployment

        $traffic_routers = TrafficRouter::query();

        if ($this->option('tr_id')) {
            $traffic_routers->where('id', $this->option('tr_id'));
        }

        if (! $this->option('force')) {
            $traffic_routers->where('prepared', false);
        }

        $traffic_routers = $traffic_routers->with('machine')->get();

        foreach ($traffic_routers as $tr) {
            $ssh = app('ssh')->toMachine($tr->machine);

            $rt = app('rt', [$tr, $ssh]);

            $rt->lock(function () use ($rt, $ssh, $tr) {
                $caddyfile_path = '/etc/caddy/db29.caddyfile';

                $ssh->exec('DEBIAN_FRONTEND=noninteractive apt install caddy curl -y');

                // on testing container env some systemd config cannot be run
                // all config applied below was test using a real machine
                // we should improve testing in the future

                $ssh->exec(
                    'touch /etc/caddy/db29.caddyfile && '.
                    'mkdir -p /etc/caddy/sites/ && '.
                    'echo '.
                    escapeshellarg('import /etc/caddy/sites/*.caddyfile').' > '.
                    '/etc/caddy/db29.caddyfile'
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
                    $ssh->exec('echo '.escapeshellarg($line).' >> /etc/caddy/db29.caddyfile');
                }

                $ssh->exec('cat /lib/systemd/system/caddy.service');

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
                    $commands[] = 'echo '.
                        escapeshellarg($override_content_line).' >> '.
                        '/etc/systemd/system/caddy.service.d/override.conf';
                }

                $ssh->exec(array_merge(
                    [
                        'mkdir -p /etc/systemd/system/caddy.service.d/',
                        'rm -rf /etc/systemd/system/caddy.service.d/override.conf',
                        'touch /etc/systemd/system/caddy.service.d/override.conf',
                        'touch '.$caddyfile_path,
                        'mkdir -p /var/lib/caddy/.config/caddy',
                        'rm -f /var/lib/caddy/.config/caddy/autosave.json',
                        'touch /var/lib/caddy/.config/caddy/autosave.json',
                    ],
                    $commands,
                ));

                if (app('env') === 'production') {
                    $ssh->exec(
                        [
                            'systemctl enable caddy',
                            'systemctl daemon-reload',
                            'systemctl stop caddy',
                            'systemctl start caddy',
                        ]
                    );
                }

                if (app('env') === 'testing') {
                    $ssh->clearOutput();

                    $ssh->exec('ps aux | grep caddy');

                    if (count($ssh->getOutput()) < 2) { // grep process and bash process
                        $ssh->exec('/usr/bin/caddy start --config '.$caddyfile_path.' --adapter caddyfile');
                    }
                }

                $rt->setup();

                TrafficRouter::whereId($tr->id)->update(['prepared' => true]);
            });
        }
    }
}
