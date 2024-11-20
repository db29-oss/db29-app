<?php

namespace App\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use K92\SshExec\SSHEngine;

class SSHServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register()
    {
        $this->app->bind('ssh', function ($app, $params) {
            return (new SSHEngine)
                ->from([
                    'ssh_privatekey_path' => config('services.ssh.ssh_privatekey_path')
                ]);
        });
    }

    public function provides()
    {
        return ['ssh'];
    }
}

