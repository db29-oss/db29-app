<?php

namespace App\Jobs;

use App\Models\Machine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PrepareMachine implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $machine_id
    ) {}

    public function handle(): void
    {
        $machine = Machine::whereId($this->machine_id)->first();

        $ssh = app('ssh')->toMachine($machine)->compute();

        if ($machine->ssh_username === 'root') {
            // we may not have sudo util by default
            $ssh->exec('DEBIAN_FRONTEND=noninteractive apt update && apt install sudo');
        } else {
            $ssh->exec('DEBIAN_FRONTEND=noninteractive sudo apt update');
        }

        $storage_conf_lines = [
            '[storage]',
            'driver = "overlay"',
            'graphroot = "'.$machine->storage_path.'podman/graphroot"',
            'runroot= "'.$machine->storage_path.'podman/runroot"',
        ];

        $commands = [];

        $md5sum_storage_conf = md5(implode(PHP_EOL, $storage_conf_lines));

        // podman
        $ssh->exec(
            array_merge(
                [
                    'sudo apt install curl git jq netcat-openbsd podman podman-compose rsync unzip -y',
                    'sudo mkdir -p '.$machine->storage_path,
                    'sudo touch /etc/containers/registries.conf.d/docker.conf',

                    'echo '.escapeshellarg('unqualified-search-registries = ["docker.io"]').' | '.
                    'sudo tee /etc/containers/registries.conf.d/docker.conf',
                    'sudo touch /etc/containers/storage.conf',
                    'sudo mkdir -p '.$machine->storage_path.'podman/graphroot',
                    'sudo mkdir -p '.$machine->storage_path.'podman/runroot',
                    'sudo rm -f /etc/containers/storage.conf',
                    'sudo touch /etc/containers/storage.conf',
                ],
                [
                    // instance
                    'sudo mkdir -p '.$machine->storage_path.'instance',
                    // www
                    'sudo mkdir -p '.$machine->storage_path.'www'
                ]
            )
        );

        $ssh->clearOutput();
        $ssh->exec('md5sum /etc/containers/storage.conf');

        if (explode(' ', $ssh->getLastLine())[0] !== $md5sum_storage_conf) {
            foreach ($storage_conf_lines as $storage_conf_line) {
                $commands[] = "echo ".
                    escapeshellarg($storage_conf_line)." | sudo tee -a /etc/containers/storage.conf";
            }
        }

        // bfq io scheduler (able control with ionice)
        if (app('env') === 'production') {
            $ssh->exec(
                [
                    'sudo touch /etc/modules-load.d/bfq.conf',
                    'echo bfq | sudo tee /etc/modules-load.d/bfq.conf',
                    'sudo touch /etc/udev/rules.d/60-scheduler.rules',
                    'echo '.
                    escapeshellarg(
                        'ACTION=="add|change", KERNEL=="sd*[!0-9]|sr*", ATTR{queue/scheduler}="bfq"'
                    ).' | sudo tee /etc/udev/rules.d/60-scheduler.rules',
                    'sudo udevadm control --reload',
                    'sudo udevadm trigger'
                ]
            );
        }

        Machine::whereId($machine->id)->update(['prepared' => true]);
    }
}
