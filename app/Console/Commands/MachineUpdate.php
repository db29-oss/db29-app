<?php

namespace App\Console\Commands;


use App\Models\Machine;
use Exception;
use Illuminate\Console\Command;

class MachineUpdate extends Command
{
    protected $signature = 'app:machine-update';

    protected $description = 'Update machine ip address';

    public function handle()
    {
        $machines = Machine::whereEnabled(true)->get();

        foreach ($machines as $machine) {
            $ip_address = gethostbyname($machine->hostname);

            if ($ip_address !== $machine->ip_address) {
                $machine->last_ip_address = $machine->ip_address;
                $machine->ip_address = $ip_address;
                $machine->save();
            }
        }
    }
}
