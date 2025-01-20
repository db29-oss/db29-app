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
                $commands[] = "echo ".
                    escapeshellarg($storage_conf_line)." >> /etc/containers/storage.conf";
            }
        }

        // podman
        $ssh->exec(
            array_merge(
                [
                    'DEBIAN_FRONTEND=noninteractive '.
                    'apt update && '.
                    'apt install curl git jq netcat-openbsd podman podman-compose rsync unzip -y',
                    'mkdir -p '.$machine->storage_path,
                    'touch /etc/containers/registries.conf.d/docker.conf',

                    'echo '.escapeshellarg('unqualified-search-registries = ["docker.io"]').' > '.
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

        // bfq io scheduler (able control with ionice)
        if (app('env') === 'production') {
            $ssh->exec(
                [
                    'touch /etc/modules-load.d/bfq.conf',
                    'echo bfq > /etc/modules-load.d/bfq.conf',
                    'touch /etc/udev/rules.d/60-scheduler.rules',
                    'echo '.
                    escapeshellarg(
                        'ACTION=="add|change", KERNEL=="sd*[!0-9]|sr*", ATTR{queue/scheduler}="bfq"'
                    ).' > /etc/udev/rules.d/60-scheduler.rules',
                    'udevadm control --reload',
                    'udevadm trigger'
                ]
            );
        }

        Machine::whereId($machine->id)->update(['prepared' => true]);
    }
}
