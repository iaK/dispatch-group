<?php

namespace Iak\DispatchGroup\Tests;

use Iak\DispatchGroup\DispatchGroupServiceProvider;
use Laravel\Horizon\HorizonServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [DispatchGroupServiceProvider::class, HorizonServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('queue.default', 'redis');
        $app['config']->set('cache.default', 'redis');
        $app['config']->set('database.redis.client', 'predis');
        $app['config']->set('database.redis.cache.database', '0');
        $app['config']->set('horizon.environments.testing', $app['config']['horizon']['environments']['local']);
        $app['config']->set('cache.prefix', '');
        $app['config']->set('horizon.prefix', '');
        $app['config']->set('database.redis.horizon.options.prefix', 'dispatch-group:database:horizon:');
        $app['config']->set('database.redis.options.prefix', 'dispatch-group:database:');
    }
}
