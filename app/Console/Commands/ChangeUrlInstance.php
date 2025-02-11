<?php

namespace App\Console\Commands;

use App\Models\Instance;
use Illuminate\Console\Command;
use ReflectionClass;

class ChangeUrlInstance extends Command
{
    protected $signature = 'app:change-url-instance {--instance_id=} {--subdomain=}';

    protected $description = 'Change url instance';

    public function handle()
    {
        if (! $this->option('instance_id')) {
            return 3;
        }

        if (! $this->option('subdomain')) {
            return 4;
        }

        $subdomain = $this->option('subdomain');

        $instance = Instance::query()
            ->whereId($this->option('instance_id'))
            ->whereStatus('rt_up')
            ->with(['machine', 'source'])
            ->first();

        if (! $instance) {
            return 5;
        }

        $machine = $instance->machine;
        $source = $instance->source;

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($source->name);

        $refl_job_class = new ReflectionClass($job_class);

        if ($refl_job_class->hasMethod('changeUrl')) {
            $method = $refl_job_class->getMethod('changeUrl');
            if ($method->getDeclaringClass()->getName() === 'App\Jobs\Instance\_0Instance_') {
                return 6;
            }
        }

        $ssh = app('ssh')->toMachine($instance->machine)->compute();

        // update dns
        if (app('env') === 'production') {
            app('cf')->updateDnsRecord(
                $instance->dns_id,
                $this->option('subdomain'),
                $instance->machine->hostname
            );
        }

        // remove old traffic router
        $ssh->exec('rm -rf /etc/caddy/sites/'.$instance->subdomain.'.caddyfile');

        // update traffic router
        Instance::query()
            ->whereId($instance->id)
            ->update([
                'subdomain' => $subdomain,
            ]);

        $instance->refresh();

        $tr_config = (new $job_class(
            instance: $instance,
            machine: $machine,
            ssh: $ssh,
        ))->changeUrl();

        $tr_config_lines = explode(PHP_EOL, $tr_config);

        foreach ($tr_config_lines as $line) {
            $ssh->exec('echo '.escapeshellarg($line).' | tee -a /etc/caddy/sites/'.$subdomain.'.caddyfile');
        }

        app('rt', [$machine->trafficRouter, $ssh])->reload();

        if (app('env') === 'production') {
            // test tls up and running
            while (true) {
                exec('curl -s -vI -L '.$subdomain.'.'.config('app.domain'), $dummy, $exit_code);

                if ($exit_code === 0) {
                    break;

                }

                sleep(3); // for DNS propagate
            }
        }
    }
}
