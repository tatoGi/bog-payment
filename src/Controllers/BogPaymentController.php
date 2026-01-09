<?php

namespace Bog\Payment\Controllers;

use Illuminate\Routing\Controller;
use Bog\Payment\Models\BogPayment;
use Bog\Payment\Services\BogAuthService;
use Bog\Payment\Services\BogPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Bog\Payment\Requests\CreateOrderRequest;
use Bog\Payment\Requests\PayWithSavedCardRequest;

class BogPaymentController extends Controller
{
    protected $bogAuth;

    protected $bogPayment;

    public function __construct(BogAuthService $bogAuth, BogPaymentService $bogPayment)
    {
        $this->bogAuth = $bogAuth;
        $this->bogPayment = $bogPayment;
    }

    /**
     * Helper to get BOG access token or throw exception
     */
    protected function token(): string
    {
        $result = $this->bogAuth->getAccessToken();
        if (!$result || empty($result['access_token'])) {
            throw new \Exception('Unable to authenticate with BOG');
        }
        return $result['access_token'];
    }

    /**
     * Test endpoint to verify requests are reaching the controller
     */
    public function testEndpoint(Request $request)
    {
        Log::info('BOG Payment - Test endpoint reached', [
            'timestamp' => \now(),
            'request_data' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        return \response()->json([
            'success' => true,
            'message' => 'Test endpoint reached successfully',
            'data' => $request->all(),
        ]);
    }

    /**
     * Obtain BOG OAuth access token using client credentials.
     */
    public function getToken()
    {
        $result = $this->bogAuth->getAccessToken();

        if (! $result || empty($result['access_token'])) {
            return \response()->json(['success' => false, 'message' => 'Unable to get token'], 500);
        }

        return \response()->json([
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'] ?? 'Bearer',
            'expires_in' => $result['expires_in'] ?? null,
        ]);
    }

    /**
     * Create order at BOG and return redirect link
     */
    /**
     * Create a new payment order with BOG
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrder(CreateOrderRequest $request)
    {
        try {
            $validated = $request->validated();
            $user = $request->user('sanctum');

            if (($validated['save_card'] ?? false) && !$user) {
                return \response()->json(['success' => false, 'message' => 'Authentication required to save card.'], 401);
            }

            $response = $this->bogPayment->createOrder(
                $this->token(),
                array_merge($validated, ['user_id' => $user->id ?? $validated['user_id'] ?? null]),
                (string) \Illuminate\Support\Str::uuid()
            );

            return \response()->json([
                'success' => true,
                'order_id' => $response['id'] ?? null,
                'redirect_url' => $response['_links']['redirect']['href'] ?? null,
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('BogPaymentController - createOrder error: ' . $e->getMessage());
            return \response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Proxy BOG receipt details by order id
     */
    public function orderDetails($orderId)
    {
        try {
            return \response()->json($this->bogPayment->getOrderDetails($this->token(), $orderId));
        } catch (\Exception $e) {
            return \response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle BOG payment callback
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleCallback(Request $request)
    {
        $orderId = $request->input('order_id');

        if (!$orderId) {
            Log::error('No order_id in BOG callback', $request->all());
            return \response()->json(['status' => 'error', 'message' => 'No order_id provided']);
        }

        try {
            // Service handles locking, API verification, DB updates, card saving, and products
            $result = $this->bogPayment->handlePaymentCallback($orderId, $request->all());

            return \response()->json($result);
        } catch (\Exception $e) {
            Log::error('BogPaymentController - Error processing BOG callback: ' . $e->getMessage(), [
                'order_id' => $orderId,
            ]);

            return \response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    // Add this method to BogPaymentController.php

    /**
     * Save card for automatic payments (subscriptions)
     *
     * @param  string  $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveCardForAutomaticPayments(Request $request, $orderId)
    {
        try {
            $result = $this->bogPayment->saveCardForAutomaticPayments($this->token(), $orderId, $request->input('idempotency_key'));
            return \response()->json($result, $result['success'] ? 200 : ($result['status'] ?? 400));
        } catch (\Exception $e) {
            return \response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject pre-authorization
     *
     * @param  string  $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectPreAuthorization(Request $request, $orderId)
    {
        try {
            $result = $this->bogPayment->rejectPreAuthorization($this->token(), $orderId, $request->all());
            return \response()->json($result, $result['success'] ? 200 : ($result['status'] ?? 400));
        } catch (\Exception $e) {
            return \response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function confirmPreAuthorization(Request $request, $orderId)
    {
        try {
            $result = $this->bogPayment->confirmPreAuthorization($this->token(), $orderId, $request->all());
            return \response()->json($result, $result['success'] ? 200 : ($result['status'] ?? 400));
        } catch (\Exception $e) {
            return \response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Process automatic payment with saved card
     *
     * @param  string  $parentOrderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function processAutomaticPayment(Request $request, $parentOrderId)
    {
        try {
            $result = $this->bogPayment->processAutomaticPayment($this->token(), $parentOrderId, $request->all());
            return \response()->json($result, $result['success'] ? 200 : ($result['status'] ?? 400));
        } catch (\Exception $e) {
            return \response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Make payment with saved card
     *
     * @param  string  $parentOrderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function payWithSavedCard(PayWithSavedCardRequest $request, $parentOrderId)
    {
        try {
            $user = $request->user('sanctum');
            if (!$user) {
                return \response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $result = $this->bogPayment->payWithSavedCard($this->token(), $parentOrderId, $request->validated(), $user->id);

            return \response()->json($result, $result['success'] ? 200 : ($result['status'] ?? 400));
        } catch (\Exception $e) {
            return \response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete saved card
     *
     * @param  string  $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSavedCard(Request $request, $orderId)
    {
        try {
            $result = $this->bogPayment->deleteSavedCard($this->token(), $orderId, $request->input('idempotency_key'));
            return \response()->json($result, $result['success'] ? 200 : ($result['status'] ?? 400));
        } catch (\Exception $e) {
            return \response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getOrderDetails($orderId)
    {
        try {
            return \response()->json([
                'success' => true,
                'data' => $this->bogPayment->getOrderDetails($this->token(), $orderId)
            ]);
        } catch (\Exception $e) {
            return \response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function saveCard(Request $request, $orderId)
    {
        try {
            $result = $this->bogPayment->saveCard($this->token(), $orderId, $request->input('idempotency_key'));
            return \response()->json([
                'success' => true,
                'message' => 'Card saved successfully',
                'data' => $result['data'],
            ]);
        } catch (\Exception $e) {
            return \response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Charge a saved card for payment
     *
     * @param  string  $parentOrderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function chargeCard(Request $request, $parentOrderId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'callback_url' => 'nullable|url',
            'external_order_id' => 'nullable|string|max:255',
            'save_card' => 'nullable|boolean',
            'pre_authorize' => 'nullable|boolean',
        ]);

        $tokenResult = $this->bogAuth->getAccessToken();
        if (! $tokenResult || empty($tokenResult['access_token'])) {
            return \response()->json(
                [
                    'success' => false,
                    'message' => 'Unable to authenticate with BOG',
                ],
                500,
            );
        }

        $paymentData = $request->only(['amount', 'currency', 'callback_url', 'external_order_id', 'save_card', 'pre_authorize']);

        $result = $this->bogPayment->chargeCard($tokenResult['access_token'], $parentOrderId, $paymentData);

        if ($result['success']) {
            return \response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'data' => $result['data'],
            ]);
        }

        return \response()->json(
            [
                'success' => false,
                'message' => 'Failed to charge saved card',
                'error' => $result['error'] ?? 'Unknown error',
                'status' => $result['status'] ?? 500,
            ],
            $result['status'] ?? 500,
        );
    }

    public function checkOrderStatus($orderId)
    {
        try {
            $payment = BogPayment::where('bog_order_id', $orderId)->firstOrFail();

            return \response()->json([
                'success' => true,
                'status' => $payment->status,
                'data' => $payment->response_data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking order status: ' . $e->getMessage(), [
                'order_id' => $orderId,
            ]);

            return \response()->json(
                [
                    'success' => false,
                    'message' => 'Order not found',
                    'status' => 404,
                ],
                404,
            );
        }
    }

    /**
     * Test BOG payment callback functionality
     * This method can be used to test if callbacks are being processed correctly
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testCallback(Request $request)
    {
        // Test with sample data
        $testOrderId = 'test_' . time();
        $testData = [
            'order_id' => $testOrderId,
            'status' => 'completed',
            'transaction_id' => 'test_txn_' . time(),
            'amount' => 100.0,
            'currency' => 'GEL',
            'test' => true,
        ];

        try {
            // Create a test payment record
            $payment = BogPayment::create([
                'bog_order_id' => $testOrderId,
                'external_order_id' => 'test_external_' . time(),
                'amount' => 100.0,
                'currency' => 'GEL',
                'status' => 'pending',
                'request_payload' => $testData,
                'response_data' => $testData,
            ]);

            return \response()->json([
                'success' => true,
                'message' => 'Test callback processed successfully',
                'test_order_id' => $testOrderId,
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Test callback failed', [
                'error' => $e->getMessage(),
            ]);

            return \response()->json(
                [
                    'success' => false,
                    'message' => 'Test callback failed: ' . $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function getUserPayments(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            if (!$user) return \response()->json(['success' => false, 'message' => 'User not authenticated'], 401);

            $payments = BogPayment::query()
                ->with(['products'])
                ->where('user_id', $user->id)
                ->orWhereRaw("JSON_EXTRACT(request_payload, '$.web_user_id') = ?", [$user->id])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return \response()->json([
                'success' => true,
                'data' => $payments->getCollection()->map(fn($p) => $this->transformPayment($p)),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total()
                ],
            ]);
        } catch (\Exception $e) {
            return \response()->json(['success' => false, 'message' => 'Failed to retrieve payments'], 500);
        }
    }

    protected function transformPayment($payment)
    {
        $products = \collect();
        if ($payment->relationLoaded('products') && $payment->products) {
            $products = $payment->products->map(function ($product) {
                $locale = \app()->getLocale();
                try {
                    $trans = method_exists($product, 'translate') ? $product->translate($locale) : null;
                    $name = $trans->title ?? $product->title ?? '';
                    $slug = $trans->slug ?? $product->slug ?? '';
                } catch (\Exception $e) {
                    $name = $product->title ?? '';
                    $slug = $product->slug ?? '';
                }

                return [
                    'id' => $product->id,
                    'name' => $name,
                    'slug' => $slug,
                    'price' => (float) ($product->price ?? 0),
                    'images' => $product->images ?? [],
                    'quantity' => $product->pivot->quantity ?? 1,
                    'unit_price' => (float) ($product->pivot->unit_price ?? $product->price ?? 0),
                    'total_price' => (float) ($product->pivot->total_price ?? ($product->price ?? 0)),
                ];
            });
        }

        return [
            'id' => $payment->id,
            'bog_order_id' => $payment->bog_order_id,
            'external_order_id' => $payment->external_order_id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'payment_method' => $payment->save_card_requested ? 'new_card' : 'saved_card',
            'products' => $products,
            'basket' => $payment->request_payload['basket'] ?? [],
            'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
            'verified_at' => $payment->verified_at ? $payment->verified_at->format('Y-m-d H:i:s') : null,
        ];
    }


    public function bulkUpdateRentalStatus(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            if (!$user) {
                return \response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $result = $this->bogPayment->bulkUpdateRentalStatus($request->input('products', []), $user->id);
            return \response()->json($result);
        } catch (\Exception $e) {
            return \response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
