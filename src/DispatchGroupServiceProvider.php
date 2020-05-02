<?php

namespace Iak\DispatchGroup;

use Illuminate\Support\ServiceProvider;

class DispatchGroupServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->app->bind('DispatchGroup', function ($app) {
            return new \Iak\DispatchGroup\DispatchGroup();
        });
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'skeleton');
    }
}
