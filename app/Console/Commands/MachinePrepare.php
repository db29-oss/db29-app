<?php

namespace App\Console\Commands;

use App\Models\Machine;
use Illuminate\Console\Command;
use K92\Phputils\BashCharEscape;

class MachinePrepare extends Command
{
    protected $signature = 'app:machine-prepare {--machine_id=} {--force}';

    protected $description = 'Basic installation/config';

    protected $machines;

    public function handle()
    {
        $machines = Machine::query();

        if ($this->option('machine_id') !== null) {
            $machines->where('id', $this->option('machine_id'));
        }

        if (! $this->option('force')) {
            $machines->where('prepared', false);
        }

        $this->machines = $machines->get();

        $this->setupPodman();
    }

    public function setupPodman()
    {
        foreach ($this->machines as $machine) {
            $ssh = app('ssh');

            // podman

            $ssh
                ->to([
                    'ssh_address' => $machine->ip_address,
                    'ssh_port' => $machine->ssh_port,
                ])
                ->exec('DEBIAN_FRONTEND=noninteractive apt install podman -y')
                ->exec('mkdir -p '.$machine->storage_path)
                ->exec('touch /etc/containers/registries.conf.d/docker.conf');

            $ssh->exec(
                'echo '.
                $ssh->lbsl."'".
                BashCharEscape::escape('unqualified-search-registries = ["docker.io"]', $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl."'".' '.
                $ssh->lbsl."> ".
                '/etc/containers/registries.conf.d/docker.conf'
            );

            $ssh->exec('touch /etc/containers/storage.conf')
                ->exec('mkdir -p '.$machine->storage_path.'podman/graphroot')
                ->exec('mkdir -p '.$machine->storage_path.'podman/runroot')
                ->exec('rm -f /etc/containers/storage.conf')
                ->exec('touch /etc/containers/storage.conf');

            $storage_conf_lines = [
                '[storage]',
                'driver = "overlay"',
                'graphroot = "'.$machine->storage_path.'podman/graphroot"',
                'runroot= "'.$machine->storage_path.'podman/runroot"',
            ];

            foreach ($storage_conf_lines as $storage_conf_line) {
                $ssh->exec(
                    'echo '.
                    $ssh->lbsl."'".
                    BashCharEscape::escape($storage_conf_line, $ssh->lbsl, $ssh->hbsl).
                    $ssh->lbsl."'".' '.
                    $ssh->lbsl.">".$ssh->lbsl."> ".
                    '/etc/containers/storage.conf'
                );
            }

            // instance

            $ssh->exec('mkdir -p '.$machine->storage_path.'instance');

            Machine::whereId($machine->id)->update(['prepared' => true]);
        }
    }
}
