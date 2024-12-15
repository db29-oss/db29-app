<?php

namespace App\Jobs;

use App\Models\Instance;
use App\Models\Machine;
use App\Models\Source;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class InitInstance implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $instance_id,
        private readonly array $reg_info = []
    ) {}

    public function handle(): void
    {
        $instance = Instance::query()
            ->whereId($this->instance_id)
            ->with([
                'machine.trafficRouter',
                'plan',
                'source'
            ])
            ->first();

        $source = $instance->source;

        $plan = $instance->plan;

        $version_templates = json_decode($source->version_templates, true);

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($source->name);

        $machine = $instance->machine; // sometime first time run bug and we dont want to reassign machine

        if (! $machine) {
            $machine = Machine::query()
                 ->where('enabled', true)
                 ->whereNull('user_id')
                 ->inRandomOrder()
                 ->with('trafficRouter')
                 ->first(); // TODO
        }

        if ($machine === null) {
            throw new Exception('DB292001: machine is null');
        }

        if ($machine->trafficRouter === null) {
            throw new Exception('DB292002: trafficRouter is null');
        }

        $traffic_router = $machine->trafficRouter;

        // init
        Instance::query()
            ->whereId($instance->id)
            ->update([
                'status' => 'init',
                'machine_id' => $machine->id
            ]);

        $instance->status = 'init';
        $instance->machine = $machine;

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

        Instance::query()
            ->whereId($instance->id)
            ->update([
                'subdomain' => $subdomain,
                'dns_id' => $dns_id,
                'status' => 'dns',
            ]);

        $instance->subdomain = $subdomain;
        $instance->dns_id = $dns_id;
        $instance->status = 'dns';

        $ssh = app('ssh')->toMachine($machine)->compute();

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
            plan: $plan,
            reg_info: $this->reg_info,
            ssh: $ssh
        ))->setUp();

        $ssh->clearOutput();

        while (true) {
            $ssh->exec('podman port '.$instance->id);

            if ($ssh->getLastLine() !== null) {
                break;
            }

            sleep(1);
        }

        $host_port = parse_url($ssh->getLastLine())['port'];

        while (true) {
            $ssh->clearOutput();

            try {
                $ssh->exec('curl -o /dev/null -s -w \'%{http_code}\' 0.0.0.0:'.$host_port);
            } catch (Exception) {
            }

            if ($ssh->getLastLine() === '200') {
                break;
            }

            sleep(1);
        }

        Instance::query()
            ->whereId($instance->id)
            ->update([
                'status' => 'ct_up',
                'version_template' => [
                    'docker_compose' => $docker_compose,
                    'port' => $host_port,
                    'tag' => $latest_version_template,
                ]
            ]);

        $instance->status = 'ct_up';
        $instance->version_template = [
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

        Instance::query()
            ->whereId($instance->id)
            ->update([
                'status' => 'rt_up', // router up
                'queue_active' => false,
                'turned_on_at' => now()
            ]);
    }
}
