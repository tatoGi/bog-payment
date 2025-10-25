# Installation Guide - BOG Payment Package

## Step-by-Step Installation

### 1. Add Package to Composer

Edit your root `composer.json` and add the repository:

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

### 2. Install Dependencies

```bash
composer require bog/payment
```

Or if you've already added it to repositories:

```bash
composer update bog/payment
```

### 3. Environment Setup

Add these variables to your `.env` file:

```env
BOG_API_BASE_URL=https://api.bog.ge/payments
BOG_AUTH_URL=https://api.bog.ge/auth/token
BOG_ORDERS_URL=https://api.bog.ge/payments/v1/ecommerce/orders
BOG_RECEIPT_URL=https://api.bog.ge/payments/v1/receipt
BOG_CALLBACK_URL=https://your-domain.com/bog/callback
BOG_CLIENT_ID=your_bog_client_id
BOG_CLIENT_SECRET=your_bog_client_secret
BOG_USER_MODEL=App\Models\WebUser
BOG_PRODUCT_MODEL=App\Models\Product
```

### 4. Publish Configuration (Optional)

```bash
php artisan vendor:publish --provider="Bog\Payment\BogPaymentServiceProvider" --tag="bog-payment-config"
```

This creates `config/bog-payment.php` which you can customize.

### 5. Run Migrations

```bash
php artisan migrate
```

This will create the following tables:
- `bog_payments`
- `bog_cards`
- `bog_payment_product` (if migrations are included)

### 6. Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
composer dump-autoload
```

## Troubleshooting

### Package Not Found

If you get "Package not found" error:

```bash
# Remove composer.lock temporarily
rm composer.lock

# Clear composer cache
composer clear-cache

# Try again
composer install
```

### Class Not Found

```bash
composer dump-autoload
```

### Config Not Loading

```bash
php artisan config:clear
php artisan config:cache
```

## Next Steps

After installation, you can use the services:

```php
use Bog\Payment\Services\BogAuthService;
use Bog\Payment\Services\BogPaymentService;
use Bog\Payment\Models\BogPayment;
use Bog\Payment\Models\BogCard;

// Get auth token
$auth = new BogAuthService();
$token = $auth->getAccessToken();

// Create payment
$payment = new BogPaymentService();
$result = $payment->createOrder($token['access_token'], [...]);

// Use models
$payments = BogPayment::all();
$cards = BogCard::where('user_id', auth()->id())->get();
```

## Verification

To verify installation:

```bash
php artisan tinker
```

Then run:

```php
config('bog-payment.client_id')
// Should return your BOG_CLIENT_ID
```

If it returns null, check your `.env` file and clear config cache.
