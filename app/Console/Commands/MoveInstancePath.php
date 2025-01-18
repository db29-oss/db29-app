<?php

namespace App\Console\Commands;

use App\Models\Instance;
use Illuminate\Console\Command;
use ReflectionClass;

class MoveInstancePath extends Command
{
    protected $signature = 'app:move-instance-path {--path=} {--instance_id=}';

    protected $description = 'Move instance path';

    public function handle()
    {
        if (! $this->option('instance_id')) {
            return 3;
        }

        if (! $this->option('path')) {
            return 4;
        }

        if (! str_ends_with($this->option('path'), '/')) {
            return 8;
        }

        $now = now();
        $sql_params = [];
        $sql = 'with '.
            'select_instance as ('.
                'select * from instances '.
                'where id = ? '. # $this->option('instance_id')
                'limit 1'.
            ') '.
            'update instances set '.
            'queue_active = ?, '. # true
            'updated_at = ? '. # $now
            'where id = (select id from select_instance) '.
            'and status = ? '. # 'ct_dw'
            'and queue_active = ? '. # false
            'returning *';

        $sql_params[] = $this->option('instance_id');
        $sql_params[] = true;
        $sql_params[] = $now;
        $sql_params[] = 'ct_dw';
        $sql_params[] = false;

        $db = app('db')->select($sql, $sql_params);

        if (! count($db)) {
            return 5;
        }

        $instance = Instance::query()
            ->whereId($this->option('instance_id'))
            ->with(['machine', 'source', 'plan'])
            ->first();

        $machine = $instance->machine;
        $source = $instance->source;

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($source->name);

        $refl_job_class = new ReflectionClass($job_class);

        if ($refl_job_class->hasMethod('movePath')) {
            $method = $refl_job_class->getMethod('movePath');
            if ($method->getDeclaringClass()->getName() === 'App\Jobs\Instance\_0Instance_') {
                return 6;
            }
        }

        if (app('env') === 'production') {
            $ssh = app('ssh')->toMachine($machine)->compute();
        }

        if (app('env') === 'production') {
            (new $job_class(
                instance: $instance,
                machine: $machine,
                ssh: $ssh,
            ))->movePath($this->option('path'));

            (new $job_class(
                instance: $instance,
                machine: $machine,
                ssh: $ssh,
            ))->tearDown();
        }

        $extra = json_decode($instance->extra, true);
        $extra['instance_path'] = $this->option('path');

        $constraint = json_decode($instance->plan->constraint, true);

        $now = now();
        $sql_params = [];
        $sql = 'with '.
            'update_machine as ('.
                'update machines set '.
                'remain_memory = remain_memory + ?, '. # $constraint['max_memory']
                'remain_cpu = remain_cpu + ?, '. # $constraint['max_cpu']
                'remain_disk = remain_disk + ?, '. # $constraint['max_disk']
                'updated_at = ? '. # $now
                'where id = ? '. # $machine->id
                'returning id'.
            ') '.
            'update instances set '.
            'queue_active = ?, '. # false
            'extra = ?, '. # json_encode($extra)
            'updated_at = ? '. # $now
            'where id = ? '. # $instance->id
            'returning *';

        $sql_params[] = $constraint['max_memory'];
        $sql_params[] = $constraint['max_cpu'];
        $sql_params[] = $constraint['max_disk'];
        $sql_params[] = $now;
        $sql_params[] = $machine->id;

        $sql_params[] = false;
        $sql_params[] = json_encode($extra);
        $sql_params[] = $now;
        $sql_params[] = $instance->id;

        $db = app('db')->select($sql, $sql_params);

        if (! count($db)) {
            return 7;
        }

        $instance->refresh();

        if (app('env') === 'production') {
            (new $job_class(
                instance: $instance,
                machine: $machine,
                ssh: $ssh,
            ))->runContainer();
        }
    }
}
