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
            cache()->store('lock')->lock('m_'.$machine->id)->get(function() use ($machine) {
                $ssh = app('ssh')
                    ->to([
                        'ssh_address' => $machine->ip_address,
                        'ssh_port' => $machine->ssh_port,
                    ]);

                $ssh->compute();

                $storage_conf_lines = [
                    '[storage]',
                    'driver = "overlay"',
                    'graphroot = "'.$machine->storage_path.'podman/graphroot"',
                    'runroot= "'.$machine->storage_path.'podman/runroot"',
                ];

                $commands = [];

                foreach ($storage_conf_lines as $storage_conf_line) {
                    $commands[] =
                        'echo '.
                        $ssh->lbsl."'".
                        BashCharEscape::escape($storage_conf_line, $ssh->lbsl, $ssh->hbsl).
                        $ssh->lbsl."'".' '.
                        $ssh->lbsl.">".$ssh->lbsl."> ".
                        '/etc/containers/storage.conf';
                }

                // podman
                $ssh->exec(
                    array_merge(
                        [
                            'DEBIAN_FRONTEND=noninteractive apt install podman -y',
                            'mkdir -p '.$machine->storage_path,
                            'touch /etc/containers/registries.conf.d/docker.conf',

                            'echo '.
                            $ssh->lbsl."'".
                            BashCharEscape::escape(
                                'unqualified-search-registries = ["docker.io"]',
                                $ssh->lbsl,
                                $ssh->hbsl
                            ).
                            $ssh->lbsl."'".' '.
                            $ssh->lbsl."> ".
                            '/etc/containers/registries.conf.d/docker.conf',
                            'touch /etc/containers/storage.conf',
                            'mkdir -p '.$machine->storage_path.'podman/graphroot',
                            'mkdir -p '.$machine->storage_path.'podman/runroot',
                            'rm -f /etc/containers/storage.conf',
                            'touch /etc/containers/storage.conf',
                        ],
                        $commands,
                        [
                            // instance
                            'mkdir -p '.$machine->storage_path.'instance',
                            // www
                            'mkdir -p '.$machine->storage_path.'www'
                        ]
                    )
                );

                Machine::whereId($machine->id)->update(['prepared' => true]);
            });
        }
    }
}
