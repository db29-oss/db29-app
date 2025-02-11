<?php

namespace App\Console\Commands;

use App\Models\Machine;
use Illuminate\Console\Command;
use phpseclib3\Crypt\PublicKeyLoader;

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
                $init_ssh = $ssh = app('ssh')->toMachine($machine)->compute();

                $get_uid_script =
                    'while read key value _; do '.
                    '[ "$key" = "Uid:" ] && { echo "$value"; break; }; '.
                    'done < /proc/self/status';

                $init_ssh->exec($get_uid_script);

                if ($init_ssh->getLastLine() !== "0") { // uid
                    /** @var \phpseclib3\Crypt\EC\PrivateKey $privatekey */
                    $privatekey = PublicKeyLoader::load($machine->ssh_privatekey);
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
            });
        }
    }
}
