<?php

namespace Bog\Payment\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Bog\Payment\BogPaymentServiceProvider;
use Illuminate\Support\Facades\Facade;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            BogPaymentServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Setup BOG configuration for testing
        $app['config']->set('services.bog', [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'api_base_url' => 'https://api.bog.ge/payments',
            'auth_url' => 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token',
            'orders_url' => 'https://api.bog.ge/payments/v1/ecommerce/orders',
            'product_model' => \Bog\Payment\Tests\Models\Product::class,
            'user_model' => \Bog\Payment\Tests\Models\User::class,
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../src/Database/Migrations');

        // Load our test migrations
        $this->loadMigrationsFrom(__DIR__ . '/Migrations');
    }
}
