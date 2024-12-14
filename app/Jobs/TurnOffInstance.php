<?php

namespace App\Jobs;

use App\Models\Instance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

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
            ->with([
                'machine.trafficRouter',
                'plan',
                'source',
                'user'
            ])
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


        // we also need to take credit from user
        // by calculate how much time instance was on/or how long since last pay
        $paid_at = Carbon::parse($instance->paid_at);

        $now = now();

        $pay_amount = (int) ceil($paid_at->diffInDays($now) * $instance->plan->price);

        $sql = 'begin;'.

            'update users set '.
            'credit = credit - '.$pay_amount.', '.
            'updated_at = \''.$now->toDateTimeString().'\' '.
            'where id = \''.$instance->user->id.'\'; '.

            'update instances set '.
            'status = \'ct_dw\', '. # 'ct_dw'
            'queue_active = false, '. # false
            'paid_at = \''.$now->toDateTimeString().'\', '. # $now->toDateTimeString()
            'turned_off_at = \''.$now->toDateTimeString().'\', '. # $now->toDateTimeString()
            'updated_at = \''.$now->toDateTimeString().'\' '. # $now->toDateTimeString()
            'where id = \''.$instance->id.'\';'. # $instance->id

            'commit;';

        app('db')->unprepared($sql);
    }
}
