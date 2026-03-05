<?php

namespace Bog\Payment\Tests;

use Bog\Payment\Models\BogPayment;
use Bog\Payment\Services\BogPaymentService;
use Bog\Payment\Tests\Models\Product;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class CreateOrderTest extends TestCase
{
    public function test_it_creates_order_against_ipay_endpoint_and_persists_payment_data()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 50,
        ]);

        Http::fake([
            'https://ipay.ge/opay/api/v1/checkout/orders' => Http::response([
                'id' => 'order_123',
                'status' => 'created',
                '_links' => [
                    'redirect' => ['href' => 'https://pay.example/redirect'],
                ],
            ], 200),
        ]);

        $service = app(BogPaymentService::class);

        $payload = [
            'callback_url' => 'https://example.com/callback',
            'external_order_id' => 'external_123',
            'purchase_units' => [
                'total_amount' => 100,
                'currency' => 'GEL',
                'basket' => [
                    [
                        'product_id' => (string) $product->id,
                        'name' => 'Test Product',
                        'quantity' => 2,
                        'unit_price' => 50,
                    ],
                ],
            ],
            'redirect_urls' => [
                'success' => 'https://example.com/success',
                'fail' => 'https://example.com/fail',
            ],
        ];

        $response = $service->createOrder('fake_access_token', $payload, 'idem-123', 'ka');

        $this->assertSame('order_123', $response['id']);
        $this->assertDatabaseHas('bog_payments', [
            'bog_order_id' => 'order_123',
            'external_order_id' => 'external_123',
            'currency' => 'GEL',
            'status' => 'created',
        ]);

        $payment = BogPayment::where('bog_order_id', 'order_123')->firstOrFail();
        $this->assertEquals(100.0, (float) $payment->amount);
        $this->assertCount(1, $payment->products);
        $this->assertSame((string) $product->id, (string) $payment->request_payload['purchase_units']['basket'][0]['product_id']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://ipay.ge/opay/api/v1/checkout/orders'
                && $request->hasHeader('Authorization')
                && $request->hasHeader('Idempotency-Key', 'idem-123')
                && $request->hasHeader('Accept-Language', 'ka');
        });
    }

    public function test_it_normalizes_legacy_purchase_units_format_before_sending_request()
    {
        Http::fake([
            'https://ipay.ge/opay/api/v1/checkout/orders' => Http::response([
                'id' => 'order_legacy_1',
                'status' => 'created',
            ], 200),
        ]);

        $service = app(BogPaymentService::class);

        $payload = [
            'callback_url' => 'https://example.com/callback',
            'purchase_units' => [
                [
                    'amount' => [
                        'value' => 30,
                        'currency_code' => 'USD',
                    ],
                    'items' => [
                        [
                            'sku' => 'sku-1',
                            'name' => 'Legacy Product',
                            'quantity' => 3,
                            'unit_price' => 10,
                        ],
                    ],
                ],
            ],
            'redirect_urls' => [
                'success' => 'https://example.com/success',
                'fail' => 'https://example.com/fail',
            ],
        ];

        $service->createOrder('fake_access_token', $payload);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return isset($body['purchase_units']['basket'][0]['product_id'])
                && $body['purchase_units']['total_amount'] === 30
                && $body['purchase_units']['currency'] === 'USD';
        });
    }
}
