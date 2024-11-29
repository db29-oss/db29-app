<?php

namespace App\Providers;

use App\Services\Router;
use App\Services\SSHEngine;
use Illuminate\Support\ServiceProvider;

class RouterServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind('rt', function ($app, $params) {
            $ssh = null;

            if (count($params)) {
                $ssh = $params[0];
            }

            return new Router($ssh);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
