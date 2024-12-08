<?php

namespace App\Providers;

use App\Services\SSHEngine;
use Illuminate\Support\ServiceProvider;

class SSHServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('ssh', function () {
            return new SSHEngine;
        });
    }

    public function provides()
    {
        return ['ssh'];
    }
}
