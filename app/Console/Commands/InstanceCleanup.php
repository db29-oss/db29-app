<?php

namespace App\Console\Commands;

use App\Jobs\TermInstance;
use App\Models\Instance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstanceCleanup extends Command
{
    protected $signature = 'app:instance-cleanup';

    protected $description = 'Cleanup unused instance after 30 days';

    public function handle()
    {
        $instances = Instance::query()
            ->whereStatus('ct_dw')
            ->where('queue_active', false)
            ->where('turned_off_at', '<', now()->subDays(30))
            ->get();

        $now = now();

        foreach ($instances as $instance) {
            $sql_params = [];
            $sql = 'update instances set '.
                'queue_active = ?, '. # true
                'updated_at = ? '. # $now
                'where id = ? '.# $instance->id
                'and status = ? '. # 'ct_dw'
                'and queue_active = ? '. # false
                'returning id';

            $sql_params[] = true;
            $sql_params[] = $now;
            $sql_params[] = $instance->id;
            $sql_params[] = 'ct_dw';
            $sql_params[] = false;

            $db = app('db')->select($sql, $sql_params);

            if (! count($db)) {
                continue;
            }

            TermInstance::dispatch($instance->id);
        }
    }
}
