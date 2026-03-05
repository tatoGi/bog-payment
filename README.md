# BOG Payment Laravel Package

A robust and clean Laravel package for integrating Bank of Georgia (BOG) payment gateway into your application. Optimized for security, race-condition prevention, and ease of use.

[![License](https://img.shields.io/packagist/l/tatogi/bog-payment-laravel)](https://packagist.org/packages/tatogi/bog-payment-laravel)
[![Latest Version](https://img.shields.io/packagist/v/tatogi/bog-payment-laravel)](https://packagist.org/packages/tatogi/bog-payment-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/tatogi/bog-payment-laravel)](https://packagist.org/packages/tatogi/bog-payment-laravel)

## 🚀 Key Features

- ✅ **Race-Condition Prevention**: Atomic locking mechanism for payment callbacks.
- ✅ **Clean Architecture**: Thin controllers with dedicated Form Requests and Service layer logic.
- ✅ **Full BOG API integration**: OAuth2, Order creation, Status tracking.
- ✅ **Card Management**: Securely save cards for 1-click future payments.
- ✅ **Automated Workflows**: Automatic product status updates upon successful payment.
- ✅ **Developer Friendly**: Highly configurable models and comprehensive logging.

## 📋 Requirements

- PHP >= 8.1
- Laravel >= 9.0 (Supports Laravel 10, 11, and 12)
- BOG Payment API Credentials

## 📦 Installation

Install the package via Composer:

```bash
composer require tatogi/bog-payment-laravel
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --provider="Bog\Payment\BogPaymentServiceProvider"
```

Run the database migrations:

```bash
php artisan migrate
```

## ⚙️ Configuration

Add your BOG credentials to your `.env` file:

```env
BOG_CLIENT_ID=your_client_id
BOG_CLIENT_SECRET=your_client_secret
BOG_CALLBACK_URL=https://your-domain.com/bog/callback

# Optional - Customize your models
# The package will automatically update these models on successful payments
BOG_USER_MODEL=App\Models\User
BOG_PRODUCT_MODEL=App\Models\Product
```

## 📖 Usage

### Creating a Payment Order

The package handles all the heavy lifting, including database records and product linking.

```php
use Bog\Payment\Services\BogPaymentService;
use Bog\Payment\Services\BogAuthService;

$auth = app(BogAuthService::class);
$paymentService = app(BogPaymentService::class);

$token = $auth->getAccessToken()['access_token'];

$payload = [
    'callback_url' => 'https://example.com/callback',
    'purchase_units' => [
        'total_amount' => 100.00,
        'currency' => 'GEL',
        'basket' => [
            [
                'product_id' => 'prod-123',
                'name' => 'Premium Item',
                'quantity' => 1,
                'unit_price' => 100.00,
            ]
        ]
    ],
    'redirect_urls' => [
        'success' => 'https://example.com/success',
        'fail' => 'https://example.com/fail',
    ],
    'save_card' => true, // Optional: request card saving
    'user_id' => auth()->id(),
];

$response = $paymentService->createOrder($token, $payload);
return redirect($response['_links']['redirect']['href']);
```

### Callback Handling (Automated)

The package includes a built-in callback handler that uses **Atomic Locks** to prevent duplicate processing if BOG sends multiple hit requests.

1. Verifies status directly with BOG API (Source of Truth).
2. Updates `bog_payments` table.
3. Saves the card (if requested).
4. Marks products as "ordered" in your database.

## 🛣️ API Endpoints

| Method     | Endpoint           | Description                   |
| :--------- | :----------------- | :---------------------------- |
| **POST**   | `/bog/callback`    | BOG Webhook handler           |
| **POST**   | `/bog/orders`      | Create a new payment order    |
| **GET**    | `/bog/orders/{id}` | Get real-time status from BOG |
| **POST**   | `/bog/cards`       | List/Add saved cards          |
| **DELETE** | `/bog/cards/{id}`  | Remove a saved card           |

## 🏗️ Architecture

- **`BogPaymentService`**: The core logic engine. Handles API communication and DB transactions.
- **`Form Requests`**: Validation is isolated for cleaner controllers.
- **`Atomic Locking`**: Uses Cache-based locks to ensure callbacks are processed exactly once.

## 🧪 Testing

The package is fully tested with PHPUnit.

```bash
composer test
```

### Test Without BOG Keys (Mock Files)

If you do not have real BOG credentials yet, you can test the full package flow with local mock files.

1. Create mock JSON files in your Laravel app:

`storage/app/mock-bog/oauth-token.json`
```json
{
  "access_token": "mock_access_token",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

`storage/app/mock-bog/order-created.json`
```json
{
  "id": "mock_order_123",
  "status": "created",
  "_links": {
    "redirect": {
      "href": "https://example.test/fake-bog-checkout"
    }
  }
}
```

`storage/app/mock-bog/payment-details.json`
```json
{
  "id": "mock_order_123",
  "status": "completed",
  "payment_method": "card",
  "amount": 100,
  "currency": "GEL"
}
```

2. Create `routes/mock-bog.php` in your app:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::post('/mock/bog/oauth/token', function () {
    return response()->json(json_decode(file_get_contents(storage_path('app/mock-bog/oauth-token.json')), true));
});

Route::post('/mock/bog/checkout/orders', function () {
    return response()->json(json_decode(file_get_contents(storage_path('app/mock-bog/order-created.json')), true));
});

Route::get('/mock/bog/checkout/payment/{orderId}', function (string $orderId) {
    $payload = json_decode(file_get_contents(storage_path('app/mock-bog/payment-details.json')), true);
    $payload['id'] = $orderId;

    return response()->json($payload);
});
```

3. Load this route file in your application:
- Laravel 11/12: add `require base_path('routes/mock-bog.php');` inside the `routes` callback in `bootstrap/app.php`.
- Laravel 9/10: load it in `RouteServiceProvider` (or require it from `routes/web.php`).

4. Point package URLs to mocks in `.env`:

```env
BOG_CLIENT_ID=mock_client
BOG_CLIENT_SECRET=mock_secret
BOG_AUTH_URL=http://127.0.0.1:8000/mock/bog/oauth/token
BOG_ORDERS_URL=http://127.0.0.1:8000/mock/bog/checkout/orders
BOG_PAYMENT_DETAILS_URL=http://127.0.0.1:8000/mock/bog/checkout/payment
BOG_CALLBACK_URL=http://127.0.0.1:8000/bog/callback
```

5. Manual mock flow:
- Call `POST /bog/orders` and confirm it returns `redirect_url`.
- Simulate callback by calling `POST /bog/callback` with `{ "order_id": "mock_order_123" }`.
- Check `bog_payments` table for `status=completed`.

This validates checkout/callback integration before switching to real BOG credentials.

## 👤 Author

**Tato**

- GitHub: [@TatoGi](https://github.com/tatoGi)
- Email: [tato.laperashvili95@gmail.com](mailto:tato.laperashvili95@gmail.com)

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

---

Made with ❤️ for the Laravel community.
