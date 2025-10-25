# BOG Payment Laravel Package

A complete Laravel package for integrating Bank of Georgia (BOG) payment gateway into your Laravel application.

[![License](https://img.shields.io/packagist/l/bog/payment)](https://packagist.org/packages/bog/payment)
[![Latest Version](https://img.shields.io/packagist/v/bog/payment)](https://packagist.org/packages/bog/payment)
[![Total Downloads](https://img.shields.io/packagist/dt/bog/payment)](https://packagist.org/packages/bog/payment)

## 🚀 Features

- ✅ Full BOG Payment API integration
- ✅ OAuth2 authentication
- ✅ Payment order management
- ✅ Save cards for future payments
- ✅ Card management (add, list, delete, set default)
- ✅ Payment history tracking
- ✅ Callback handling
- ✅ Pre-authorization support
- ✅ Product linking
- ✅ Complete database migrations
- ✅ Ready-to-use controllers and routes

## 📋 Requirements

- PHP >= 8.0
- Laravel >= 9.0, <= 12.0
- BOG Payment API credentials

## 📦 Installation

Install the package via Composer:

```bash
composer require bog/payment
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Bog\Payment\BogPaymentServiceProvider" --tag="bog-payment-config"
```

Run migrations:

```bash
php artisan migrate
```

## ⚙️ Configuration

Add your BOG credentials to the `.env` file:

```env
BOG_API_BASE_URL=https://api.bog.ge/payments
BOG_AUTH_URL=https://api.bog.ge/auth/token
BOG_ORDERS_URL=https://api.bog.ge/payments/v1/ecommerce/orders
BOG_CLIENT_ID=your_client_id
BOG_CLIENT_SECRET=your_client_secret
BOG_CALLBACK_URL=https://your-domain.com/bog/callback

# Optional - Customize your models
BOG_USER_MODEL=App\Models\User
BOG_PRODUCT_MODEL=App\Models\Product
```

## 📖 Usage

### Basic Example

```php
use Bog\Payment\Services\BogAuthService;
use Bog\Payment\Services\BogPaymentService;

// Get access token
$auth = new BogAuthService();
$token = $auth->getAccessToken();

// Create payment order
$paymentService = new BogPaymentService();
$orderData = [
    'callback_url' => 'https://your-domain.com/bog/callback',
    'purchase_units' => [
        'total_amount' => 100.00,
        'currency' => 'GEL',
        'basket' => [
            [
                'product_id' => '123',
                'name' => 'Product Name',
                'quantity' => 1,
                'unit_price' => 100.00,
            ]
        ]
    ],
    'redirect_urls' => [
        'success' => 'https://your-domain.com/success',
        'fail' => 'https://your-domain.com/fail',
    ],
];

$result = $paymentService->createOrder($token['access_token'], $orderData);
```

### Using Models

```php
use Bog\Payment\Models\BogPayment;
use Bog\Payment\Models\BogCard;

// Get payment by order ID
$payment = BogPayment::where('bog_order_id', $orderId)->first();

// Get user's saved cards
$cards = BogCard::where('user_id', auth()->id())->get();

// Check payment status
if ($payment && $payment->status === 'completed') {
    // Payment successful
}
```

### Using Controllers

The package includes ready-to-use controllers:

```php
use Bog\Payment\Controllers\BogPaymentController;
use Bog\Payment\Controllers\BogCardController;

// In your routes file
Route::post('/bog/orders', [BogPaymentController::class, 'createOrder']);
Route::get('/bog/cards', [BogCardController::class, 'listCards']);
```

## 🗄️ Database

The package automatically creates the following tables:

- `bog_payments` - Payment transactions
- `bog_cards` - Saved payment cards
- `bog_payment_product` - Payment-product relationships

## 🛣️ Available Routes

### Payment Routes

```
POST   /bog/callback                     - Handle BOG callback
POST   /bog/orders                       - Create payment order
GET    /bog/orders/{orderId}             - Get order details
POST   /bog/orders/{orderId}/save-card   - Save card
POST   /bog/orders/{parentOrderId}/charge - Charge saved card
```

### Card Management Routes

```
POST   /bog/cards/add                    - Add new card
GET    /bog/cards                        - List all cards
DELETE /bog/cards/{cardId}               - Delete card
POST   /bog/cards/{cardId}/set-default   - Set default card
```

To disable these routes, set `BOG_ROUTES_ENABLED=false` in your `.env` file.

## 🏗️ Package Structure

```
src/
├── Models/
│   ├── BogPayment.php       # Payment model
│   └── BogCard.php          # Card model
├── Services/
│   ├── BogAuthService.php   # OAuth2 authentication
│   └── BogPaymentService.php # Payment operations
├── Controllers/
│   ├── BogPaymentController.php
│   └── BogCardController.php
├── Exceptions/
│   ├── BogPaymentException.php
│   ├── AuthenticationException.php
│   └── ...
└── Database/Migrations/
    ├── create_bog_payments_table.php
    ├── create_bog_cards_table.php
    └── ...
```

## 🧪 Testing

Test the package installation:

```bash
php artisan tinker
```

```php
use Bog\Payment\Services\BogAuthService;

$auth = new BogAuthService();
$token = $auth->getAccessToken();
dd($token);
```

## 🐛 Troubleshooting

### Class not found

```bash
composer dump-autoload
php artisan config:clear
```

### Config not loading

```bash
php artisan vendor:publish --provider="Bog\Payment\BogPaymentServiceProvider" --force
php artisan config:cache
```

### Routes not working

Check that `BOG_ROUTES_ENABLED=true` in your `.env` file.

## 📝 Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on what has changed recently.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 🔐 Security

If you discover any security-related issues, please email the maintainer instead of using the issue tracker.

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## 🆘 Support

For support, email support@example.com or open an issue on GitHub.

## 📚 Additional Resources

- [BOG Payment API Documentation](https://api.bog.ge/documentation)
- [Laravel Documentation](https://laravel.com/docs)

## 👤 Author

**Your Name**

- GitHub: [@TatoGi](https://github.com/tatoGi/bog-payment)
- Email: tato.laperashvili95@gmail.com

## 🙏 Credits

Special thanks to Bank of Georgia for providing the payment gateway API.

---

Made with ❤️ for the Laravel community