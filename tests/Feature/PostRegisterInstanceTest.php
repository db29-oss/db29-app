<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Source;
use Tests\TestCase;

class PostRegisterInstanceTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $ssh_port = setup_container('db29_post_register_instance');

        $ssh_privatekey_path = sys_get_temp_dir().'/db29_post_register_instance';

        config(['services.ssh.ssh_privatekey_path' => $ssh_privatekey_path]);

        $u = User::factory()->create();

        auth()->login($u);

        $s = new Source;
        $s->name = 'planka';
        $s->enabled = true;
        $s->save();

        $response = $this->post('instance/register', [
            'source' => 'planka'
        ]);


        // clean up
        cleanup_container('db29_post_register_instance');
    }
}
