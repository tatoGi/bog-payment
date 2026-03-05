<?php

return [
    'api_base_url' => env('BOG_API_BASE_URL', 'https://ipay.ge/opay/api/v1'),
    'api_url' => env('BOG_API_URL', env('BOG_API_BASE_URL', 'https://ipay.ge/opay/api/v1')),
    'auth_url' => env('BOG_AUTH_URL', 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token'),
    'orders_url' => env('BOG_ORDERS_URL', 'https://ipay.ge/opay/api/v1/checkout/orders'),
    'payment_details_url' => env('BOG_PAYMENT_DETAILS_URL', 'https://ipay.ge/opay/api/v1/checkout/payment'),
    'receipt_url' => env('BOG_RECEIPT_URL', 'https://ipay.ge/opay/api/v1/receipt'),
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
