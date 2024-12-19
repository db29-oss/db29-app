<?php

namespace App\Jobs;

use App\Models\Instance;
use Exception;
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
            ->with([
                'machine.trafficRouter',
                'plan',
                'source',
            ])
            ->first();

        $source = $instance->source;

        $plan = $instance->plan;

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
            plan: $plan,
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

        if ($old_rule === false) {
            $rt->addRule($new_rule);
        } else {
            $rt->updateRule($old_rule, $new_rule);
        }

        $constraint = json_decode($instance->plan->constraint, true);

        $now = now();

        $sql =
            'begin; '.

            'update instances set '.
            'status = \'rt_up\', '.
            'queue_active = false, '.
            'turned_on_at = \''.$now->toDateTimeString().'\', '.
            'updated_at = \''.$now->toDateTimeString().'\' '.
            'where id = \''.$instance->id.'\'; '.

            'update machines set '.
            'remain_cpu = remain_cpu - '.$constraint['max_cpu'].', '.
            'remain_memory = remain_memory - '.$constraint['max_memory'].', '.
            'updated_at = \''.$now->toDateTimeString().'\' '.
            'where id = \''.$machine->id.'\'; '.

            'commit;';

        app('db')->unprepared($sql);
    }
}
