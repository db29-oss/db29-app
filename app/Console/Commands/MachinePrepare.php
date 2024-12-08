<?php

namespace App\Console\Commands;

use App\Models\Machine;
use Illuminate\Console\Command;

class MachinePrepare extends Command
{
    protected $signature = 'app:machine-prepare {--machine_id=} {--force}';

    protected $description = 'Basic installation/config';

    public function handle()
    {
        $machines = Machine::query();

        if ($this->option('machine_id') !== null) {
            $machines->where('id', $this->option('machine_id'));
        }

        if (! $this->option('force')) {
            $machines->where('prepared', false);
        }

        $machines = $machines->get();

        foreach ($machines as $machine) {
            cache()->store('lock')->lock('m_'.$machine->id)->get(function() use ($machine) {
                $ssh = app('ssh')->toMachine($machine)->compute();

                $ssh->exec('touch /etc/containers/storage.conf');

                $storage_conf_lines = [
                    '[storage]',
                    'driver = "overlay"',
                    'graphroot = "'.$machine->storage_path.'podman/graphroot"',
                    'runroot= "'.$machine->storage_path.'podman/runroot"',
                ];

                $commands = [];

                $md5sum_storage_conf = md5(implode("\n", $storage_conf_lines));

                $ssh->clearOutput();
                $ssh->exec('md5sum /etc/containers/storage.conf');

                if (explode(' ', $ssh->getLastLine())[0] !== $md5sum_storage_conf) {
                    foreach ($storage_conf_lines as $storage_conf_line) {
                        $commands[] =
                            'echo '.
                            $ssh->lbsl."'".
                            bce($storage_conf_line, $ssh->lbsl, $ssh->hbsl).
                            $ssh->lbsl."'".' '.
                            $ssh->lbsl.">".$ssh->lbsl."> ".
                            '/etc/containers/storage.conf';
                    }
                }

                // podman
                $ssh->exec(
                    array_merge(
                        [
                            'DEBIAN_FRONTEND=noninteractive apt install podman podman-compose -y',
                            'mkdir -p '.$machine->storage_path,
                            'touch /etc/containers/registries.conf.d/docker.conf',

                            'echo '.
                            $ssh->lbsl."'".
                            bce(
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
