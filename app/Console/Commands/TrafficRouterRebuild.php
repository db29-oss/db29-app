<?php

namespace App\Console\Commands;

use App\Models\TrafficRouter;
use Artisan;
use Illuminate\Console\Command;

class TrafficRouterRebuild extends Command
{
    protected $signature = 'app:traffic-router-rebuild {--tr_id=}';

    protected $description = 'Rebuild all rules on traffic routers';

    public function handle()
    {
        $trs = TrafficRouter::query();

        if ($this->option('tr_id')) {
            $trs->whereId($this->option('tr_id'));
        }

        $trs = $trs->with('machine')->get();

        foreach ($trs as $tr) {
            $ssh = app('ssh')->toMachine($tr->machine)->compute();
            $rt = app('rt', [$tr, $ssh]);

            $rt->wipecf();

            $rt->setup();
        }

        Artisan::call('app:route-update');
    }
}
