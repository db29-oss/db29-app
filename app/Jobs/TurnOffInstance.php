<?php

namespace App\Jobs;

use App\Models\Instance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TurnOffInstance implements ShouldQueue
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

        // rt_dw
        $old_rule = $rt->findRuleBySubdomainName($instance->subdomain);

        if ($old_rule === false) {
            logger()->error('DB292006: unable find rule by subdomain', [
                'instance_id' => $instance->id,
                'subdomain' => $instance->subdomain,
            ]);

            throw new Exception('DB292007: unable find rule by subdomain');
        }

        $new_rule =
            [
                'match' => [
                    [
                        'host' => [$instance->subdomain.'.'.config('app.domain')]
                    ]
                ],
                "handle" => [
                    [
                        "handler" => "static_response",
                        "status_code" => 200,
                        "body" => "instance is currently off - turn instance on at ".config('app.domain')
                    ]
                ]
            ];

        $rt->updateRule($old_rule, $new_rule);

        // ct_dw
        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($instance->source->name);

        (new $job_class(
            instance: $instance,
            machine: $machine,
            ssh: $ssh,
        ))->turnOff();

        $now = now();
        $sql_params = [];
        $sql = 'update instances set '.
            'status = ?, '. # 'ct_dw'
            'queue_active = ?, '. # false
            'updated_at = ? '. # $now
            'where id = ?'; # $instance->id

        $sql_params[] = 'ct_dw';
        $sql_params[] = false;
        $sql_params[] = $now;
        $sql_params[] = $instance->id;

        app('db')->select($sql, $sql_params);
    }
}
