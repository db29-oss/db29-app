<?php

namespace App\Console\Commands;

use App\Jobs\TurnOffInstance;
use App\Models\Instance;
use Illuminate\Console\Command;

class TurnOffFreeInstance extends Command
{
    protected $signature = 'app:turn-off-free-instance';

    protected $description = 'Turn off free instance';

    public function handle()
    {
        $now = now();
        $sql_params = [];
        $sql = "update instances set ".
            "queue_active = ?, ". # true
            "updated_at = ? ". # $now
            "where queue_active = ? ". # false
            "and paid_at < ? ". # (clone $now)->subDay()
            "returning id";

        $sql_params[] = true;
        $sql_params[] = $now;
        $sql_params[] = false;
        $sql_params[] = (clone $now)->subDay();

        $instances = app('db')->select($sql, $sql_params);

        foreach ($instances as $instance) {
            TurnOffInstance::dispatch($instance->id);
        }
    }
}
