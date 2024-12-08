<?php

namespace App\Services;

use App\Models\Machine;
use K92\SshExec\SSHEngine as SE;

class SSHEngine extends SE {
    public function toMachine(Machine $machine)
    {
        $ssh_privatekey_path = storage_path('app/private/'.$machine->id);

        if (! file_exists($ssh_privatekey_path)) {
            $touch = touch($ssh_privatekey_path);

            if (! $touch) {
                throw new Exception('DB292003: unable touch ssh private key');
            }

            $chmod = chmod($ssh_privatekey_path, 0600);

            if (! $chmod) {
                throw new Exception('DB292004: unable chmod ssh private key');
            }

            $file_put_contents = file_put_contents($ssh_privatekey_path, $machine->ssh_privatekey);

            if ($file_put_contents === false) {
                throw new Exception('DB292005: unable file_put_contents ssh private key');
            }
        }

        return $this->from([
            'ssh_privatekey_path' => $ssh_privatekey_path
        ])->to([
            'ssh_address' => $machine->ip_address,
            'ssh_port' => $machine->ssh_port,
        ]);
    }
}
