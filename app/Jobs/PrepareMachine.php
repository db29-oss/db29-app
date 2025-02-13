<?php

namespace App\Jobs;

use App\Models\Machine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use phpseclib3\Crypt\PublicKeyLoader;

class PrepareMachine implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $machine_id
    ) {}

    public function handle(): void
    {
        $machine = Machine::whereId($this->machine_id)->first();

        $init_ssh = $ssh = app('ssh')->toMachine($machine)->compute();

        $get_uid_script =
            'while read key value _; do '.
            '[ "$key" = "Uid:" ] && { echo "$value"; break; }; '.
            'done < /proc/self/status';

        $init_ssh->exec($get_uid_script);

        if ($init_ssh->getLastLine() !== "0") { // uid
            $privatekey = PublicKeyLoader::load($machine->ssh_privatekey);
            /** @var \phpseclib3\Crypt\EC\PrivateKey $privatekey */
            $publickey = $privatekey->getPublicKey();
            $ssh_publickey = $publickey->toString('OpenSSH', ['comment' => '']);

            $init_ssh->exec('sudo mkdir -p /root/.ssh');
            $init_ssh->exec('sudo touch /root/.ssh/authorized_keys');
            $init_ssh->exec(
                'echo '.escapeshellarg($ssh_publickey).' | '.
                'sudo tee -a /root/.ssh/authorized_keys'
            );

            Machine::whereId($machine->id)->update(['ssh_username' => 'root']);

            $machine->refresh();

            $ssh = $init_ssh = app('ssh')->toMachine($machine)->compute();
        }

        $ssh->exec('DEBIAN_FRONTEND=noninteractive apt update');

        $storage_conf_lines = [
            '[storage]',
            'driver = "overlay"',
            'graphroot = "'.$machine->storage_path.'podman/graphroot"',
            'runroot= "'.$machine->storage_path.'podman/runroot"',
        ];

        $md5sum_storage_conf = md5(implode(PHP_EOL, $storage_conf_lines));

        // podman
        $ssh->exec(
            array_merge(
                [
                    'apt install curl git jq netcat-openbsd podman podman-compose rsync unzip -y',
                    'mkdir -p '.$machine->storage_path,
                    'touch /etc/containers/registries.conf.d/docker.conf',

                    'echo '.escapeshellarg('unqualified-search-registries = ["docker.io"]').' | '.
                    'tee /etc/containers/registries.conf.d/docker.conf',
                    'touch /etc/containers/storage.conf',
                    'mkdir -p '.$machine->storage_path.'podman/graphroot',
                    'mkdir -p '.$machine->storage_path.'podman/runroot',
                    'rm -f /etc/containers/storage.conf',
                    'touch /etc/containers/storage.conf',
                ],
                [
                    // instance
                    'mkdir -p '.$machine->storage_path.'instance',
                    // www
                    'mkdir -p '.$machine->storage_path.'www'
                ]
            )
        );

        $ssh->clearOutput();
        $ssh->exec('md5sum /etc/containers/storage.conf');

        $commands = [];

        if (explode(' ', $ssh->getLastLine())[0] !== $md5sum_storage_conf) {
            foreach ($storage_conf_lines as $storage_conf_line) {
                $commands[] = "echo ".
                    escapeshellarg($storage_conf_line)." | tee -a /etc/containers/storage.conf";
            }
        }

        $ssh->exec($commands);

        // bfq io scheduler (able control with ionice)
        if (app('env') === 'production') {
            $ssh->exec(
                [
                    'touch /etc/modules-load.d/bfq.conf',
                    'echo bfq | tee /etc/modules-load.d/bfq.conf',
                    'touch /etc/udev/rules.d/60-scheduler.rules',
                    'echo '.
                    escapeshellarg(
                        'ACTION=="add|change", KERNEL=="sd*[!0-9]|sr*", ATTR{queue/scheduler}="bfq"'
                    ).' | tee /etc/udev/rules.d/60-scheduler.rules',
                    'udevadm control --reload',
                    'udevadm trigger'
                ]
            );
        }

        Machine::whereId($machine->id)->update(['prepared' => true]);
    }
}
