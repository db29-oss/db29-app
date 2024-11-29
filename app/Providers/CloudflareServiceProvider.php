<?php

namespace App\Providers;

use App\Services\Cloudflare;
use Illuminate\Support\ServiceProvider;

class CloudflareServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('cf', function () {
            return new Cloudflare(
                config('services.cloudflare.zone_id'),
                config('services.cloudflare.zone_token')
            );
        });
    }

    public function provides()
    {
        return ['cf'];
    }
}
