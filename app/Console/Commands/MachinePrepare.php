<?php

namespace App\Console\Commands;

use App\Models\Machine;
use Illuminate\Console\Command;
use K92\Phputils\BashCharEscape;

class MachinePrepare extends Command
{
    protected $signature = 'app:machine-prepare {--machine_id=}';

    protected $description = 'Basic installation/config';

    protected $machines;

    public function handle()
    {
        $machines = Machine::query();

        if ($this->option('machine_id') !== null) {
            $machines->where('id', $this->option('machine_id'));
        }

        $this->machines = $machines->get();

        $this->setupPodman();
    }

    public function setupPodman()
    {
        foreach ($this->machines as $machine) {
            $ssh = app('ssh');
            $ssh
                ->to([
                    'ssh_address' => $machine->ip_address,
                    'ssh_port' => $machine->ssh_port,
                ])
                ->exec('apt install podman -y')
                ->exec('mkdir '.$machine->storage_path.' -p');

            Machine::whereId($machine->id)->update(['prepared' => true]);
        }
    }
}
