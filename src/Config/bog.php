<?php

return [
    'api_base_url' => env('BOG_API_BASE_URL', 'https://api.bog.ge/payments'),
    'auth_url' => env('BOG_AUTH_URL'),
    'orders_url' => env('BOG_ORDERS_URL', 'https://api.bog.ge/payments/v1/ecommerce/orders'),
    'receipt_url' => env('BOG_RECEIPT_URL', 'https://api.bog.ge/payments/v1/receipt'),
    'callback_url' => env('BOG_CALLBACK_URL'),
    'client_id' => env('BOG_CLIENT_ID'),
    'client_secret' => env('BOG_CLIENT_SECRET'),

    // Additional configuration
    'user_model' => env('BOG_USER_MODEL', 'App\Models\WebUser'),
    'product_model' => env('BOG_PRODUCT_MODEL', 'App\Models\Product'),

    // Routes configuration
    'routes' => [
        'enabled' => env('BOG_ROUTES_ENABLED', true),
    ],
];