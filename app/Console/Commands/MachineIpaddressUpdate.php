<?php

namespace App\Console\Commands;

use App\Models\Machine;
use Exception;
use Illuminate\Console\Command;

class MachineIpaddressUpdate extends Command
{
    protected $signature = 'app:machine-ipaddress-update {--machine_id=}';

    protected $description = 'Update ipaddress for machine';

    public function handle()
    {
        $machines = Machine::query()
            ->whereEnabled(true);

        if ($this->option('machine_id')) {
            $machines = $machines->whereId($this->option('machine_id'));
        }

        $machines = $machines->get();

        foreach ($machines as $machine) {
            if ($machine->hostname === null) {
                continue;
            }

            $dns_get_record = dns_get_record($machine->hostname, DNS_A);

            if (count($dns_get_record) === 0) {
                continue;
            }

            $ip_address = $dns_get_record[0]['ip'];

            if ($ip_address === $machine->ip_address) {
                continue;
            }

            $output = [];
            exec('curl -s '.$ip_address.':80/ping', $output, $exit_code);

            if ($exit_code !== 0) {
                $ssh_privatekey_path = storage_path('app/private/'.$machine->id);

                if (! file_exists($ssh_privatekey_path)) {
                    touch($ssh_privatekey_path);
                    chmod($ssh_privatekey_path, 0600);
                    file_put_contents($ssh_privatekey_path, $machine->ssh_privatekey);
                }

                $ssh = app('ssh')->from([
                    'ssh_privatekey_path' => $ssh_privatekey_path
                ])->to([
                    'ssh_address' => $ip_address,
                    'ssh_port' => $machine->ssh_port,
                ]);

                try {
                    $ssh->exec('echo testing_connection');
                } catch (Exception) {
                    continue;
                }
            }

            if ($output[0] === (string) $machine->id) {
                Machine::query()->update([
                    'ip_address' => $ip_address,
                    'last_ip_address' => filter_var(
                        $machine->ip_address, FILTER_VALIDATE_IP
                    ) ? $machine->ip_address : null
                ]);
            }
        }
    }
}
