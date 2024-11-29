<?php

namespace App\Providers;

use App\Services\Yaml;
use Illuminate\Support\ServiceProvider;

class YamlServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind('yml', function () {
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
