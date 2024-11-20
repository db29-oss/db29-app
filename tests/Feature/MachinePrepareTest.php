<?php

namespace Tests\Feature;

use App\Models\Machine;
use Artisan;
use K92\SshExec\SSHEngine;
use Tests\TestCase;

class MachinePrepareTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $ssh_port = setup_container('db29_prepare_machine');

        $ssh_privatekey_path = sys_get_temp_dir().'/db29_prepare_machine';

        $m = new Machine;
        $m->ip_address = '127.0.0.1';
        $m->ssh_port = $ssh_port;
        $m->storage_path = '/opt/podman/';
        $m->save();

        config(['services.ssh.ssh_privatekey_path' => $ssh_privatekey_path]);

        $ssh = new SSHEngine;

        $ssh
            ->from([
                'ssh_privatekey_path' => $ssh_privatekey_path
            ])
           ->to([
               'ssh_port' => $ssh_port,
           ])
           ->exec('ls -1 /opt/');

        $this->assertEquals(0, count($ssh->getOutput()));

        Artisan::call('app:machine-prepare');

        $ssh = new SSHEngine;

        $ssh
            ->from([
                'ssh_privatekey_path' => $ssh_privatekey_path
            ])
           ->to([
               'ssh_port' => $ssh_port,
           ])
           ->exec('ls -1 /opt/');

        $this->assertEquals(1, count($ssh->getOutput()));

        // clean up
        cleanup_container('db29_prepare_machine');
    }
}
