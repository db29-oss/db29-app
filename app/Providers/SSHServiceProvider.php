<?php

namespace App\Providers;

use App\Services\SSHEngine;
use Illuminate\Support\ServiceProvider;

class SSHServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('ssh', function ($app, array $endpoint = []) {
            return (new SSHEngine)
                ->from(array_merge(
                    [
                        'ssh_privatekey_path' => config('services.ssh.ssh_privatekey_path')
                    ],
                    $endpoint
                ));
        });
    }

    public function provides()
    {
        return ['ssh'];
    }
}
