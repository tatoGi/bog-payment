<?php

namespace Bog\Payment;

use Illuminate\Support\ServiceProvider;

class BogPaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config file
        $this->mergeConfigFrom(
            __DIR__ . '/Config/bog.php',
            'services.bog'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/Config/bog.php' => config_path('bog-payment.php'),
        ], 'bog-payment-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/Database/Migrations' => database_path('migrations'),
        ], 'bog-payment-migrations');
    }
}
