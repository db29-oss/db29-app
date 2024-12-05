<?php

namespace App\Jobs;

use App\Models\Instance;
use App\Models\Machine;
use App\Models\Source;
use App\Services\SSHEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

class InitInstance implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $instance_id,
        private readonly array $reg_info = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $instance = Instance::query()
            ->whereId($this->instance_id)
            ->with('source')
            ->with('machine.trafficRouter')
            ->first();

        $source = $instance->source;

        $version_templates = json_decode($source->version_templates, true);

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($source->name);

        $resources = $job_class::initialResourceConsumption();

        $machine = $instance->machine;

        if (! $machine) {
            $machine = Machine::query()
                 ->where('enabled', true)
                 ->whereNull('user_id')
                 ->inRandomOrder()
                 ->with('trafficRouter')
                 ->first(); // TODO
        }

        $traffic_router = $machine->trafficRouter;

        // init
        $instance->status = 'init';
        $instance->machine_id = $machine->id;
        $instance->save();

        // dns
        $subdomain = $instance->subdomain;

        if ($subdomain === null) {
            $subdomain = str(str()->random(8))->lower()->toString();
        }

        $dns_id = $instance->dns_id;

        if (! $dns_id) {
            $dns_id = str(str()->random(32))->lower()->toString(); // for testing

            if (app('env') === 'production') {
                $dns_id = app('cf')
                    ->addDnsRecord(
                        $subdomain,
                        $machine->ip_address,
                        ['comment' => $this->instance_id]
                    );
            }
        }

        $instance->subdomain = $subdomain;
        $instance->dns_id = $dns_id;
        $instance->status = 'dns';
        $instance->save();

        $ssh = app('ssh')->to([
            'ssh_address' => $machine->ip_address,
            'ssh_port' => $machine->ssh_port,
        ])->compute();

        // get deploy information
        $latest_version_template = null;
        $docker_compose = null;

        foreach ($version_templates as $vt_idx => $version_template) {
            if ($latest_version_template === null) {
                $latest_version_template = $version_template['tag'];

                if (array_key_exists('docker_compose', $version_templates[$vt_idx])) {
                    $docker_compose = $version_templates[$vt_idx]['docker_compose'];
                }

                continue;
            }

            if ($version_template['tag'] > $latest_version_template) {
                $latest_version_template = $version_template;
                $docker_compose = $version_templates[$vt_idx]['docker_compose'];
            }
        }

        $host_port = (new $job_class(
            docker_compose: $docker_compose,
            instance: $instance,
            latest_version_template: $latest_version_template,
            machine: $machine,
            reg_info: $this->reg_info,
            ssh: $ssh
        ))->setUp();

        $ssh->clearOutput();

        $ssh->exec('podman port '.$instance->id);

        $host_port = parse_url($ssh->getLastLine())['port'];

        if ($host_port === 0) {
            logger()->debug('DB292001: debug podman host port bug', [
                'host_port' => $host_port,
                'ssh_last_line' => $ssh->getLastLine(),
            ]);

            throw new Exception('DB292002: debug podman host port bug');
        }

        $instance->status = 'ct_up';
        $instance->version_template =
            [
                'docker_compose' => $docker_compose,
                'port' => $host_port,
                'tag' => $latest_version_template,
            ];

        // router
        $rule =
            [
                'match' => [
                    [
                        'host' => [$subdomain.'.'.config('app.domain')]
                    ]
                ],
                'handle' => [
                    [
                        'handler' => 'reverse_proxy',
                        'upstreams' => [
                            [
                                'dial' => '127.0.0.1:'.$host_port
                            ]
                        ]
                    ]
                ]
            ];

        app('rt', [$traffic_router, $ssh])->addRule($rule);

        $instance->status = 'rt_up'; // router up
        $instance->queue_active = false; // could be delete by user
        $instance->save();
    }
}
