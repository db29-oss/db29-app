<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\Yaml\Yaml;

class YamlServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('yml', function () {
            return new Yaml;
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
