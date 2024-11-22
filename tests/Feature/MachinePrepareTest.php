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

        $ssh_port = setup_container('db29_machine_prepare');

        $ssh_privatekey_path = sys_get_temp_dir().'/db29_machine_prepare';

        $m = new Machine;
        $m->ip_address = '127.0.0.1';
        $m->ssh_port = $ssh_port;
        $m->storage_path = '/opt/randomdirname/';
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

        $this->assertEquals(false, Machine::whereId($m->id)->first()->prepared);

        Artisan::call('app:machine-prepare');

        $this->assertEquals(true, Machine::whereId($m->id)->first()->prepared);

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
        cleanup_container('db29_machine_prepare');
    }
}
