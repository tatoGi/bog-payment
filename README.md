# BOG Payment Laravel Package

Complete Laravel package for integrating Bank of Georgia (BOG) payment gateway.

## 📋 Features

- ✅ Full BOG Payment API integration
- ✅ OAuth2 authentication  
- ✅ Payment order management
- ✅ Save cards for future payments
- ✅ Card management (CRUD operations)
- ✅ Payment history tracking
- ✅ Callback handling
- ✅ Database migrations included
- ✅ Product linking support

## 🚀 Quick Start

### Installation

1. Add to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/bog-payment",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "bog/payment": "*"
    }
}
```

2. Install the package:

```bash
composer install
```

3. Configure environment variables in `.env`:

```env
BOG_API_BASE_URL=https://api.bog.ge/payments
BOG_AUTH_URL=https://api.bog.ge/auth/token
BOG_ORDERS_URL=https://api.bog.ge/payments/v1/ecommerce/orders
BOG_CLIENT_ID=your_client_id
BOG_CLIENT_SECRET=your_client_secret
BOG_CALLBACK_URL=https://your-domain.com/bog/callback
BOG_USER_MODEL=App\Models\WebUser
BOG_PRODUCT_MODEL=App\Models\Product
```

4. Publish configuration:

```bash
php artisan vendor:publish --provider="Bog\Payment\BogPaymentServiceProvider" --tag="bog-payment-config"
```

5. Run migrations:

```bash
php artisan migrate
```

## 📖 Usage

### Creating a Payment Order

```php
use Bog\Payment\Services\BogAuthService;
use Bog\Payment\Services\BogPaymentService;

$auth = new BogAuthService();
$payment = new BogPaymentService();

$token = $auth->getAccessToken();

$result = $payment->createOrder($token['access_token'], [
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
]);
```

### Using Models

```php
use Bog\Payment\Models\BogPayment;
use Bog\Payment\Models\BogCard;

// Get payment
$payment = BogPayment::where('bog_order_id', $orderId)->first();

// Get user's cards
$cards = BogCard::where('user_id', auth()->id())->get();
```

## 📁 Package Contents

```
packages/bog-payment/
├── src/
│   ├── Models/
│   │   ├── BogPayment.php
│   │   └── BogCard.php
│   ├── Services/
│   │   ├── BogAuthService.php
│   │   └── BogPaymentService.php
│   ├── Controllers/
│   │   ├── BogPaymentController.php
│   │   └── BogCardController.php
│   ├── Exceptions/
│   │   ├── BogPaymentException.php
│   │   ├── AuthenticationException.php
│   │   ├── OrderCreationException.php
│   │   ├── CallbackException.php
│   │   └── CardException.php
│   ├── Routes/
│   │   └── api.php
│   ├── Database/Migrations/
│   │   ├── create_bog_payments_table.php
│   │   ├── create_bog_cards_table.php
│   │   ├── create_bog_payment_product_table.php
│   │   └── ... (additional migrations)
│   ├── Config/
│   │   └── bog.php
│   └── BogPaymentServiceProvider.php
├── composer.json
├── README.md
└── CHANGELOG.md
```

## 🗄️ Database Tables

After running migrations, the package creates:

- `bog_payments` - Stores payment transactions
- `bog_cards` - Stores saved payment cards
- `bog_payment_product` - Pivot table for payment-product relationships

## 🎯 Requirements

- PHP 8.0+
- Laravel 9.x, 10.x, 11.x, or 12.x
- BOG Payment API credentials

## 📝 License

MIT

## 💡 Support

For issues and questions, please contact the package maintainer or open an issue in the repository.

## 🔄 Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and changes.
