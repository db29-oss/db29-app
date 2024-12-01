<?php

namespace App\Providers;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\ServiceProvider;

class DispatcherServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('dispatcher', function ($app) {
            return new Dispatcher($app);
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
