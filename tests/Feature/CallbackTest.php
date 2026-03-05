<?php

namespace Bog\Payment\Tests;

use Bog\Payment\Models\BogPayment;
use Bog\Payment\Services\BogPaymentService;
use Bog\Payment\Tests\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CallbackTest extends TestCase
{
    public function test_it_can_process_successful_callback_with_race_condition_prevention()
    {
        // 1. Setup Data
        $orderId = 'bog_order_123';
        $payment = BogPayment::create([
            'bog_order_id' => $orderId,
            'amount' => 100,
            'currency' => 'GEL',
            'status' => 'created',
            'request_payload' => [
                'basket' => [
                    ['product_id' => 1, 'quantity' => 1, 'unit_price' => 100, 'name' => 'Test Product']
                ]
            ]
        ]);

        // 2. Mock BOG API responses
        Http::fake([
            '*/auth/realms/bog/protocol/openid-connect/token' => Http::response(['access_token' => 'fake_token']),
            '*/checkout/payment/*' => Http::response([
                'id' => $orderId,
                'status' => 'completed',
                'amount' => 100,
                'currency' => 'GEL'
            ])
        ]);

        // 3. Execution
        $service = app(BogPaymentService::class);
        $result = $service->handlePaymentCallback($orderId, ['order_id' => $orderId]);

        // 4. Verification
        $this->assertEquals('success', $result['status']);

        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->verified_at);
    }

    public function test_it_blocks_concurrent_callbacks_for_the_same_order()
    {
        $orderId = 'concurrent_order_456';
        BogPayment::create(['bog_order_id' => $orderId, 'amount' => 50, 'status' => 'created']);

        // Simulate an existing lock
        $lock = Cache::lock("bog_payment_{$orderId}", 10);
        $lock->get();

        $service = app(BogPaymentService::class);

        // This should fail because the lock is already held
        $result = $service->handlePaymentCallback($orderId, []);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Could not acquire lock', $result['message']);
    }

    public function test_it_marks_products_as_ordered_when_basket_is_inside_purchase_units()
    {
        $product = Product::create([
            'name' => 'Rental Item',
            'price' => 20,
        ]);

        $orderId = 'purchase_units_order_789';
        BogPayment::create([
            'bog_order_id' => $orderId,
            'amount' => 20,
            'currency' => 'GEL',
            'status' => 'created',
            'request_payload' => [
                'purchase_units' => [
                    'basket' => [
                        [
                            'product_id' => (string) $product->id,
                            'quantity' => 1,
                            'unit_price' => 20,
                            'name' => 'Rental Item',
                        ],
                    ],
                ],
            ],
        ]);

        Http::fake([
            '*/auth/realms/bog/protocol/openid-connect/token' => Http::response(['access_token' => 'fake_token']),
            '*/checkout/payment/*' => Http::response([
                'id' => $orderId,
                'status' => 'completed',
            ]),
        ]);

        $service = app(BogPaymentService::class);
        $result = $service->handlePaymentCallback($orderId, ['order_id' => $orderId]);

        $this->assertEquals('success', $result['status']);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_ordered' => true,
        ]);
    }
}
