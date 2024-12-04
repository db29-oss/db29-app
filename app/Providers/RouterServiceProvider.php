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
            $traffic_router = null;
            $ssh = null;

            if (array_key_exists(0, $params)) {
                $traffic_router = $params[0];
            }

            if (array_key_exists(1, $params)) {
                $ssh = $params[1];
            }

            return new Router($traffic_router, $ssh);
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
