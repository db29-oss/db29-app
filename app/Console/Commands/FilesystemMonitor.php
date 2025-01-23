<?php

namespace App\Console\Commands;

use App\Models\Machine;
use Illuminate\Console\Command;

class FilesystemMonitor extends Command
{
    protected $signature = 'app:filesystem-monitor';

    protected $description = 'Monitor filesystem';

    public function handle()
    {
        $machines = Machine::whereNull('user_id')->get();

        foreach ($machines as $machine) {
            $ssh = app('ssh')->toMachine($machine);

            $ssh->exec('btrfs device stats '.$machine->storage_path);

            foreach ($ssh->getOutput() as $line) {
                if (substr(trim($line), -2) !== ' 0') {
                    exec(
                        'curl -H "Content-Type: application/json" '.
                        '-X POST -d "{\"content\": \"DB292008: DISK ERROR READ '.$machine->hostname.'\"}" '.
                        config('services.discord.monitor_webhook')
                    );
                }
            }
        }
    }
}
