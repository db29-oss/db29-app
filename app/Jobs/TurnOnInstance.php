<?php

namespace App\Jobs;

use App\Models\Instance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TurnOnInstance implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $instance_id
    ) {}

    public function handle(): void
    {
        $instance = Instance::query()
            ->whereId($this->instance_id)
            ->with('source')
            ->with('machine.trafficRouter')
            ->first();

        $source = $instance->source;

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($source->name);

        $machine = $instance->machine;
        $traffic_router = $instance->machine->trafficRouter;

        $ssh = app('ssh')->toMachine($machine)->compute();

        $rt = app('rt', [$traffic_router, $ssh]);

        // ct_up
        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($instance->source->name);

        (new $job_class(
            instance: $instance,
            machine: $machine,
            ssh: $ssh,
        ))->turnOn();

        while (true) {
            $ssh->exec('podman port '.$instance->id);

            if ($ssh->getLastLine() !== null) {
                break;
            }

            sleep(1);
        }

        $host_port = parse_url($ssh->getLastLine())['port'];

        // rt_up
        $old_rule = $rt->findRuleBySubdomainName($instance->subdomain);

        if ($old_rule === false) {
            logger()->error('DB292008: unable find rule by subdomain', [
                'instance_id' => $instance->id,
                'subdomain' => $instance->subdomain,
            ]);

            throw new Exception('DB292009: unable find rule by subdomain');
        }

        $new_rule =
            [
                'match' => [
                    [
                        'host' => [$instance->subdomain.'.'.config('app.domain')]
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

        $rt->updateRule($old_rule, $new_rule);

        $now = now();
        $sql_params = [];
        $sql = 'update instances set '.
            'status = ?, '. # 'rt_up'
            'queue_active = ?, '. # false
            'updated_at = ? '. # $now
            'where id = ?'; # $instance->id

        $sql_params[] = 'rt_up';
        $sql_params[] = false;
        $sql_params[] = $now;
        $sql_params[] = $instance->id;

        app('db')->select($sql, $sql_params);
    }
}
