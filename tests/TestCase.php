<?php

namespace Bog\Payment\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Bog\Payment\BogPaymentServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../src/Database/Migrations');
        $this->loadMigrationsFrom(__DIR__ . '/Migrations');
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
        $config = [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'api_base_url' => 'https://ipay.ge/opay/api/v1',
            'auth_url' => 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token',
            'orders_url' => 'https://ipay.ge/opay/api/v1/checkout/orders',
            'payment_details_url' => 'https://ipay.ge/opay/api/v1/checkout/payment',
            'product_model' => \Bog\Payment\Tests\Models\Product::class,
            'user_model' => \Bog\Payment\Tests\Models\User::class,
        ];

        $app['config']->set('bog-payment', $config);
        $app['config']->set('services.bog', $config);
    }
}
