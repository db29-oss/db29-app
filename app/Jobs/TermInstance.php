<?php

namespace App\Jobs;

use App\Models\Instance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TermInstance implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $instance_id
    ) {}

    public function handle(): void
    {
        $instance = Instance::query()
            ->whereId($this->instance_id)
            ->with('user')
            ->with('source')
            ->with('machine.trafficRouter')
            ->first();

        $machine = $instance->machine;
        $traffic_router = $instance->machine->trafficRouter;

        $ssh = app('ssh')->toMachine($machine)->compute();

        // rt_dw
        app('rt', [$traffic_router, $ssh])->deleteRuleBySubdomainName($instance->subdomain);

        // dns_dw
        if (app('env') === 'production') {
            app('cf')->deleteDnsRecord($instance->dns_id);
        }

        // ct_dw
        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($instance->source->name);

        (new $job_class(
            instance: $instance,
            machine: $machine,
            ssh: $ssh,
        ))->tearDown();

        $now = now()->toDateTimeString();
        $sql = 'begin; '.
            'update users set '.
            'instance_count = instance_count - 1, '.
            'updated_at = \''.$now.'\' '.
            'where id = \''.$instance->user->id.'\'; '.
            'delete from instances '.
            'where id = \''.$instance->id.'\'; '.
            'commit;';

        app('db')->unprepared($sql);
    }
}
