<?php

namespace Bog\Payment\Services;

use Bog\Payment\Models\BogPayment;
use Bog\Payment\Support\BogConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Bog\Payment\Models\BogCard;

class BogPaymentService
{
    /**
     * Last HTTP status code from the API
     *
     * @var int|null
     */
    protected $lastHttpStatus = null;

    /**
     * Last error message from the API
     *
     * @var string|null
     */
    protected $lastError = null;

    /**
     * Get the last HTTP status code from the API
     */
    public function getLastHttpStatus(): ?int
    {
        return $this->lastHttpStatus;
    }

    /**
     * Get the last error message from the API
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Make a payment with a saved card
     */
    public function payWithSavedCard(string $accessToken, string $parentOrderId, array $paymentData, $userId): array
    {
        $endpoint = "/v1/orders/{$parentOrderId}/payments";

        $payload = [
            'callback_url' => $paymentData['callback_url'],
            'amount' => $paymentData['amount'],
            'basket' => $paymentData['basket'],
            'language' => $paymentData['language'] ?? 'ka',
        ];

        try {
            return DB::transaction(function () use ($accessToken, $parentOrderId, $payload, $endpoint, $paymentData, $userId) {
                $response = $this->makeRequest('POST', $endpoint, $accessToken, $payload);

                if (isset($response['error']) || !$response) {
                    throw new \Exception($response['error']['message'] ?? 'Payment with saved card failed');
                }

                $bogOrderId = $response['order_id'] ?? ('saved_' . uniqid());

                $payment = BogPayment::create([
                    'bog_order_id' => $bogOrderId,
                    'external_order_id' => $paymentData['external_order_id'] ?? ('order_' . \time()),
                    'user_id' => $userId,
                    'amount' => $paymentData['amount'],
                    'currency' => $paymentData['currency'] ?? 'GEL',
                    'status' => $response['status'] ?? 'created',
                    'request_payload' => array_merge($payload, ['parent_order_id' => $parentOrderId]),
                    'response_data' => $response,
                    'save_card_requested' => false,
                ]);

                $this->attachProductsToPayment($payment, $paymentData['basket'] ?? []);

                // If payment is already completed, mark products as ordered immediately
                if (in_array(strtolower($payment->status), ['completed', 'approved', 'succeeded'])) {
                    $this->markProductsAsOrdered($payment);
                }

                return [
                    'success' => true,
                    'data' => $response,
                    'payment_id' => $payment->id,
                    'message' => 'Payment with saved card initiated successfully',
                ];
            });
        } catch (\Exception $e) {
            Log::error('BOG Payment with saved card failed: ' . $e->getMessage(), [
                'parent_order_id' => $parentOrderId,
                'user_id' => $userId,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => $this->getLastHttpStatus() ?? 400,
            ];
        }
    }

    /**
     * Make an HTTP request to the BOG API
     */
    protected function makeRequest(string $method, string $url, string $accessToken, array $data = [], array $headers = []): ?array
    {
        $this->lastHttpStatus = null;
        $this->lastError = null;

        try {
            // Get BOG API base URL from config
            $baseUrl = BogConfig::apiBaseUrl();

            // Build full URL (handle both absolute and relative URLs)
            $fullUrl = str_starts_with($url, 'http') ? $url : $baseUrl . $url;

            $http = Http::withToken($accessToken)
                ->withHeaders($headers)
                ->withOptions(options: ['debug' => \config('app.debug')])
                ->acceptJson();

            $response = $http->$method($fullUrl, $data);
            $this->lastHttpStatus = $response->status();

            if ($response->successful()) {
                return $response->json();
            }

            $this->lastError = $response->body();
            Log::error('BOG API Error', [
                'status' => $response->status(),
                'url' => $fullUrl,
                'response' => $response->body(),
                'request_data' => $data,
                'headers' => $headers,
            ]);

            return null;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            // Build full URL for error logging
            $baseUrl = BogConfig::apiBaseUrl();
            $fullUrl = str_starts_with($url, 'http') ? $url : $baseUrl . $url;

            Log::error('BOG API Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'url' => $fullUrl,
                'data' => $data,
            ]);

            return null;
        }
    }

    /**
     * Create an order on BOG payment API and save to database
     *
     * @return array
     */
    public function createOrder(string $accessToken, array $payload, ?string $idempotencyKey = null, ?string $acceptLanguage = 'en')
    {
        try {
            // Generate a temporary order ID if not provided
            $tempOrderId = 'temp_' . uniqid();
            $transformedPayload = $this->transformPayloadForBogApi($payload);
            $basket = $this->extractBasket($transformedPayload);

            // Create a database record for the payment
            $bogPayment = new BogPayment([
                'bog_order_id' => $tempOrderId,
                'external_order_id' => $payload['external_order_id'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
                'amount' => $this->extractTotalAmount($transformedPayload),
                'currency' => $this->extractCurrency($transformedPayload),
                'status' => 'pending',
                'request_payload' => $transformedPayload,
                'save_card_requested' => $payload['save_card'] ?? false,
            ]);

            // Log when save_card is requested
            if ($payload['save_card'] ?? false) {
                if (empty($payload['user_id'])) {
                    Log::warning('BOG Payment - Save card requested but user not authenticated', [
                        'external_order_id' => $payload['external_order_id'] ?? null,
                        'user_id' => $payload['user_id'] ?? null,
                        'save_card' => $payload['save_card'],
                        'warning' => 'Card cannot be saved without user authentication',
                    ]);
                } else {
                    Log::info('BOG Payment - Save card requested during order creation', [
                        'external_order_id' => $payload['external_order_id'] ?? null,
                        'user_id' => $payload['user_id'] ?? null,
                        'save_card' => $payload['save_card'],
                    ]);
                }
            }

            // Handle database operations in a transaction
            return DB::transaction(function () use ($accessToken, $tempOrderId, $transformedPayload, $bogPayment, $basket, $idempotencyKey, $acceptLanguage) {
                // Save the payment record
                if (! $bogPayment->save()) {
                    throw new \Exception('Failed to save payment to database');
                }

                // Attach products if available
                $this->attachProductsToPayment($bogPayment, $basket);

                $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ];

                if (! empty($idempotencyKey)) {
                    $headers['Idempotency-Key'] = $idempotencyKey;
                }

                if (! empty($acceptLanguage)) {
                    $headers['Accept-Language'] = $acceptLanguage;
                }

                // Make the API request
                $response = Http::withToken($accessToken)
                    ->withHeaders($headers)
                    ->withOptions(['debug' => \config('app.debug')])
                    ->timeout(30)
                    ->post(BogConfig::ordersUrl(), $transformedPayload);

                $responseBody = $response->json() ?? $response->body();
                $this->lastHttpStatus = $response->status();

                if (! $response->successful()) {
                    throw new \Exception($responseBody['error_description'] ?? $responseBody['message'] ?? 'Unknown error');
                }

                // Update the payment record with the BOG order ID
                $bogPayment->update([
                    'bog_order_id' => $responseBody['id'] ?? $tempOrderId,
                    'response_data' => $responseBody,
                    'redirect_url' => $responseBody['_links']['redirect']['href'] ?? null,
                    'status' => $responseBody['status'] ?? 'created',
                ]);

                return $responseBody;
            });
        } catch (\Exception $e) {
            Log::error('BOG Payment - Error in createOrder', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update the payment status to failed if we have a record
            if (isset($bogPayment) && $bogPayment instanceof BogPayment) {
                try {
                    $bogPayment->update([
                        'status' => 'failed',
                        'response_data' => array_merge(
                            (array) ($bogPayment->response_data ?? []),
                            ['error' => $e->getMessage()]
                        ),
                    ]);
                } catch (\Exception $updateError) {
                    Log::error('Failed to update payment status after error', [
                        'payment_id' => $bogPayment->id ?? null,
                        'error' => $updateError->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * Transform payload to match BOG iPay API expectations.
     */
    private function transformPayloadForBogApi(array $payload): array
    {
        if (! isset($payload['purchase_units']) || ! is_array($payload['purchase_units'])) {
            return $payload;
        }

        $transformedPayload = $payload;
        $purchaseUnits = $payload['purchase_units'];
        $firstUnit = $purchaseUnits;

        if ($this->isSequentialArray($purchaseUnits)) {
            $firstUnit = $purchaseUnits[0] ?? [];
        }

        $items = $firstUnit['basket'] ?? $firstUnit['items'] ?? [];
        if (! is_array($items)) {
            $items = [];
        }

        $basket = array_map(function ($item) {
            return $this->normalizeBasketItem(is_array($item) ? $item : []);
        }, $items);

        $totalAmount = $firstUnit['total_amount']
            ?? $firstUnit['amount']['value']
            ?? $this->calculateBasketTotal($basket);

        $currency = $firstUnit['currency']
            ?? $firstUnit['amount']['currency_code']
            ?? 'GEL';

        $transformedPayload['purchase_units'] = [
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'basket' => $basket,
        ];

        return $transformedPayload;
    }

    private function normalizeBasketItem(array $item): array
    {
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unitPrice = (float) ($item['unit_price'] ?? 0);

        return [
            'product_id' => (string) ($item['product_id'] ?? $item['sku'] ?? uniqid('product_', true)),
            'name' => $item['name'] ?? 'Product',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $item['total_amount'] ?? ($quantity * $unitPrice),
        ];
    }

    private function isSequentialArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    private function extractBasket(array $payload): array
    {
        $purchaseUnitsBasket = $payload['purchase_units']['basket'] ?? null;
        if (is_array($purchaseUnitsBasket)) {
            return $purchaseUnitsBasket;
        }

        $flatBasket = $payload['basket'] ?? null;

        return is_array($flatBasket) ? $flatBasket : [];
    }

    private function extractTotalAmount(array $payload): float
    {
        return (float) (
            $payload['purchase_units']['total_amount']
            ?? $payload['purchase_units'][0]['amount']['value']
            ?? 0
        );
    }

    private function extractCurrency(array $payload): string
    {
        return (string) (
            $payload['purchase_units']['currency']
            ?? $payload['purchase_units'][0]['amount']['currency_code']
            ?? 'GEL'
        );
    }

    private function calculateBasketTotal(array $basket): float
    {
        $total = 0.0;
        foreach ($basket as $item) {
            $quantity = (int) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $total += $quantity * $unitPrice;
        }

        return $total;
    }

    /**
     * Get order details from BOG API
     */
    public function getOrderDetails(string $accessToken, string $orderId): ?array
    {
        $url = BogConfig::paymentDetailsUrl($orderId);

        $response = $this->makeRequest('get', $url, $accessToken);

        if (! $response) {
            Log::error('BOG API - Failed to get order details', [
                'order_id' => $orderId,
                'status_code' => $this->lastHttpStatus,
                'error' => $this->lastError,
            ]);
        }

        return $response;
    }

    /**
     * Verify callback signature from BOG
     */
    public function verifyCallbackSignature(string $signature, string $data, string $publicKeyPath): bool
    {
        try {
            $publicKey = file_get_contents($publicKeyPath);
            $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($publicKey, 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";

            $publicKeyResource = openssl_pkey_get_public($publicKey);
            if ($publicKeyResource === false) {
                Log::error('BOG API - Invalid public key');

                return false;
            }

            $result = openssl_verify(
                $data,
                base64_decode($signature),
                $publicKeyResource,
                'sha256WithRSAEncryption'
            );

            openssl_free_key($publicKeyResource);

            return $result === 1;
        } catch (\Exception $e) {
            Log::error('BOG API - Error verifying signature', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Process automatic payment with saved card
     */
    public function processAutomaticPayment(string $accessToken, string $parentOrderId, array $data): array
    {
        $url = BogConfig::ordersUrl() . "/{$parentOrderId}/payments";

        $payload = [
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'GEL',
            'capture_method' => 'AUTOMATIC',
            'save_card' => $data['save_card'] ?? false,
            'pre_authorize' => $data['pre_authorize'] ?? false,
        ];

        $response = $this->makeRequest('post', $url, $accessToken, $payload);

        if (! $response) {
            Log::error('BOG API - Failed to process automatic payment', [
                'order_id' => $parentOrderId,
                'status_code' => $this->lastHttpStatus,
                'error' => $this->lastError,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process payment',
                'error' => $this->lastError,
                'status_code' => $this->lastHttpStatus,
            ];
        }

        return [
            'success' => true,
            'data' => $response,
        ];
    }

    /**
     * Save card for automatic payments (subscriptions)
     */
    public function saveCardForAutomaticPayments(string $accessToken, string $orderId, ?string $idempotencyKey = null): array
    {
        $url = BogConfig::ordersUrl() . "/{$orderId}/save-card";

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $response = $this->makeRequest('POST', $url, $accessToken, [], $headers);

        if (! $response) {
            Log::error('BOG API - Failed to save card for automatic payments', [
                'order_id' => $orderId,
                'status_code' => $this->lastHttpStatus,
                'error' => $this->lastError,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to save card for automatic payments',
                'error' => $this->lastError,
                'status' => $this->lastHttpStatus ?? 500,
            ];
        }

        Log::info('BOG API - Card saved for automatic payments', [
            'order_id' => $orderId,
            'response' => $response,
        ]);

        return [
            'success' => true,
            'data' => $response,
            'message' => 'Card saved successfully for automatic payments',
        ];
    }

    /**
     * Reject a pre-authorization
     *
     * @return array
     */
    public function rejectPreAuthorization(string $accessToken, string $orderId, array $data = [])
    {
        $endpoint = BogConfig::ordersUrl() . "/{$orderId}/preauthorization/reject";

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post($endpoint, $data);

        $this->lastHttpStatus = $response->status();

        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json(),
            ];
        }

        $this->lastError = $response->json('error_description', 'Unknown error');

        return [
            'success' => false,
            'status' => $this->lastHttpStatus,
            'message' => $this->lastError,
        ];
    }

    /**
     * Confirm a pre-authorization
     *
     * @return array
     */
    public function confirmPreAuthorization(string $accessToken, string $orderId, array $data = [])
    {
        $endpoint = BogConfig::ordersUrl() . "/{$orderId}/preauthorization/confirm";

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post($endpoint, $data);

        $this->lastHttpStatus = $response->status();

        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json(),
            ];
        }

        $this->lastError = $response->json('error_description', 'Unknown error');

        return [
            'success' => false,
            'status' => $this->lastHttpStatus,
            'message' => $this->lastError,
        ];
    }

    /**
     * Delete a saved card
     */
    public function deleteSavedCard(string $accessToken, string $orderId, string $idempotencyKey): array
    {
        $endpoint = BogConfig::ordersUrl() . "/{$orderId}/saved-card";

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->delete($endpoint);

        $this->lastHttpStatus = $response->status();

        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json(),
            ];
        }

        $this->lastError = $response->json('error_description', 'Unknown error');

        return [
            'success' => false,
            'status' => $this->lastHttpStatus,
            'message' => $this->lastError,
        ];
    }

    /**
     * Save card details during payment process
     */
    public function saveCard(string $accessToken, string $orderId, ?string $idempotencyKey = null): array
    {
        $url = BogConfig::ordersUrl() . "/{$orderId}/save-card";

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        Log::debug('BOG API - Save Card', [
            'url' => $url,
            'order_id' => $orderId,
            'headers' => array_merge($headers, ['Authorization' => 'Bearer [REDACTED]']),
            'access_token_length' => strlen($accessToken),
        ]);

        $response = $this->makeRequest('POST', $url, $accessToken, [], $headers);

        if (! $response) {
            Log::error('BOG API - Failed to save card', [
                'order_id' => $orderId,
                'status_code' => $this->lastHttpStatus,
                'error' => $this->lastError,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to save card',
                'error' => $this->lastError,
                'status' => $this->lastHttpStatus ?? 500,
            ];
        }

        Log::info('BOG API - Card saved successfully', [
            'order_id' => $orderId,
            'response' => $response,
        ]);

        // Log the actual response structure for debugging
        Log::info('BOG API - Card save response structure', [
            'order_id' => $orderId,
            'response_keys' => array_keys($response),
            'response_data' => $response,
        ]);

        // Check if the response contains card data
        if (isset($response['card_token']) || isset($response['card_mask']) || isset($response['card_type'])) {
            Log::info('BOG Payment - Card details found in save card response', [
                'order_id' => $orderId,
                'card_token' => $response['card_token'] ?? null,
                'card_mask' => $response['card_mask'] ?? null,
                'card_type' => $response['card_type'] ?? null,
                'expiry_month' => $response['expiry_month'] ?? null,
                'expiry_year' => $response['expiry_year'] ?? null,
            ]);
        } else {
            Log::warning('BOG Payment - No card details found in save card response', [
                'order_id' => $orderId,
                'response_keys' => array_keys($response),
                'full_response' => $response,
            ]);
        }

        return [
            'success' => true,
            'data' => $response,
            'message' => 'Card saved successfully',
        ];
    }

    /**
     * Charge a saved card for payment
     */
    public function chargeCard(string $accessToken, string $parentOrderId, array $paymentData): array
    {
        $url = BogConfig::ordersUrl() . "/{$parentOrderId}/payments";

        $payload = [
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'GEL',
            'capture_method' => 'AUTOMATIC',
            'save_card' => $paymentData['save_card'] ?? false,
            'pre_authorize' => $paymentData['pre_authorize'] ?? false,
            'callback_url' => $paymentData['callback_url'] ?? null,
            'external_order_id' => $paymentData['external_order_id'] ?? null,
        ];

        $response = $this->makeRequest('POST', $url, $accessToken, $payload);

        if (! $response) {
            Log::error('BOG API - Failed to charge saved card', [
                'parent_order_id' => $parentOrderId,
                'status_code' => $this->lastHttpStatus,
                'error' => $this->lastError,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to charge saved card',
                'error' => $this->lastError,
                'status' => $this->lastHttpStatus ?? 500,
            ];
        }

        Log::info('BOG API - Saved card charged successfully', [
            'parent_order_id' => $parentOrderId,
            'response' => $response,
        ]);

        return [
            'success' => true,
            'data' => $response,
            'message' => 'Saved card charged successfully',
        ];
    }

    public function handlePaymentCallback(string $orderId, array $callbackData)
    {
        try {
            return Cache::lock("bog_payment_{$orderId}", 30)->block(10, function () use ($orderId, $callbackData) {
                return DB::transaction(function () use ($orderId, $callbackData) {
                    // Find the payment record in the package's table
                    $payment = BogPayment::where('bog_order_id', $orderId)->first();

                    if (!$payment) {
                        Log::error('BogPaymentService - Payment not found for order_id: ' . $orderId);
                        return ['status' => 'error', 'message' => 'Payment not found'];
                    }

                    // If already completed/failed and verified, skip (idempotency)
                    if ($payment->verified_at && in_array(strtolower($payment->status), ['completed', 'approved', 'succeeded'])) {
                        Log::info('BogPaymentService - Payment already processed', ['order_id' => $orderId]);
                        return ['status' => 'success', 'already_processed' => true];
                    }

                    try {
                        // Truth source: Check status with BOG API directly
                        $authService = \app(BogAuthService::class);
                        $token = $authService->getAccessToken();

                        if (!$token || empty($token['access_token'])) {
                            throw new \Exception('Failed to get BOG access token');
                        }

                        $orderDetails = $this->getOrderDetails($token['access_token'], $orderId);

                        if (!$orderDetails) {
                            throw new \Exception('Failed to fetch order details from BOG');
                        }

                        $newStatus = $orderDetails['status'] ?? 'failed';

                        Log::info('BogPaymentService - Updating payment status', [
                            'order_id' => $orderId,
                            'current_status' => $payment->status,
                            'new_status' => $newStatus
                        ]);

                        // Update payment status
                        $payment->update([
                            'status' => $newStatus,
                            'response_data' => $orderDetails,
                            'callback_data' => $callbackData,
                            'verified_at' => \now(),
                        ]);

                        $isSuccess = in_array(strtolower($newStatus), ['completed', 'approved', 'succeeded']);

                        // Handle card saving if requested and payment successful
                        if ($isSuccess && $payment->save_card_requested && $payment->user_id) {
                            $this->handleSaveCard($token['access_token'], $orderId, $payment->user_id);
                        }

                        // Mark products as ordered if payment successful
                        if ($isSuccess) {
                            $this->markProductsAsOrdered($payment);
                        }

                        return ['status' => 'success', 'payment_status' => $newStatus];
                    } catch (\Exception $e) {
                        Log::error('BogPaymentService - Error handling callback: ' . $e->getMessage(), [
                            'order_id' => $orderId,
                            'exception' => $e
                        ]);

                        $payment->update([
                            'status' => 'error',
                            'error_message' => $e->getMessage(),
                        ]);

                        throw $e;
                    }
                });
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('BogPaymentService - Could not acquire lock for payment callback', ['order_id' => $orderId]);
            return ['status' => 'error', 'message' => 'Could not acquire lock, another process is handling this payment'];
        }
    }

    /**
     * Handle card saving logic
     */
    protected function handleSaveCard(string $accessToken, string $orderId, $userId)
    {
        try {
            $saveCardResult = $this->saveCard($accessToken, $orderId);

            if ($saveCardResult['success']) {
                $cardData = $saveCardResult['data'];

                // Use the package's BogCard model
                BogCard::updateOrCreate(
                    ['card_token' => $cardData['card_token'] ?? null, 'user_id' => $userId],
                    [
                        'card_mask' => $cardData['card_mask'] ?? null,
                        'card_type' => $cardData['card_type'] ?? null,
                        'expiry_month' => $cardData['expiry_month'] ?? null,
                        'expiry_year' => $cardData['expiry_year'] ?? null,
                        'is_default' => false,
                        'metadata' => $cardData,
                        'parent_order_id' => $orderId,
                    ]
                );

                Log::info('BogPaymentService - Card successfully saved', ['order_id' => $orderId, 'user_id' => $userId]);
            }
        } catch (\Exception $e) {
            Log::error('BogPaymentService - Error saving card: ' . $e->getMessage(), ['order_id' => $orderId]);
        }
    }

    /**
     * Mark products as ordered (isolated product logic)
     */
    protected function markProductsAsOrdered(BogPayment $payment)
    {
        $basket = $this->extractBasket($payment->request_payload ?? []);
        if (empty($basket)) return;

        $webUserId = $payment->request_payload['web_user_id'] ?? $payment->user_id;
        $pivotData = [];

        foreach ($basket as $item) {
            if (isset($item['product_id'])) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'] ?? 1;
                $unitPrice = $item['unit_price'] ?? 0;

                $pivotData[$productId] = [
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $quantity * $unitPrice,
                ];
            }
        }

        if (!empty($pivotData)) {
            // Attach/Sync products to the payment record (pivot table)
            if (method_exists($payment, 'products')) {
                $payment->products()->syncWithoutDetaching($pivotData);
            }
        }

        // Update individual Product models in the app
        $productModelClass = BogConfig::get('product_model', 'App\Models\Product');
        if (class_exists($productModelClass)) {
            foreach ($basket as $item) {
                if (isset($item['product_id'])) {
                    $product = $productModelClass::find($item['product_id']);
                    if ($product) {
                        $updateData = [
                            'is_ordered' => true,
                            'ordered_at' => \now(),
                            'ordered_by' => $webUserId,
                        ];

                        if (isset($item['rental_start_date'], $item['rental_end_date'])) {
                            $updateData['is_rented'] = true;
                            $updateData['rented_at'] = \now();
                            $updateData['rental_start_date'] = $item['rental_start_date'];
                            $updateData['rental_end_date'] = $item['rental_end_date'];
                        }

                        $product->update($updateData);
                    }
                }
            }
        }
    }

    /**
     * Helper to attach products to a payment via pivot table
     */
    protected function attachProductsToPayment(BogPayment $payment, array $basket)
    {
        if (empty($basket)) return;

        $pivotData = [];
        foreach ($basket as $item) {
            if (isset($item['product_id'])) {
                $pivotData[$item['product_id']] = [
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total_price' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
                ];
            }
        }

        if (!empty($pivotData) && method_exists($payment, 'products')) {
            $payment->products()->syncWithoutDetaching($pivotData);
        }
    }

    /**
     * Bulk update product rental status
     */
    public function bulkUpdateRentalStatus(array $productsData, $userId): array
    {
        $updatedProducts = [];
        $errors = [];
        $productModelClass = BogConfig::get('product_model', 'App\Models\Product');

        if (!class_exists($productModelClass)) {
            return ['success' => false, 'message' => "Product model class {$productModelClass} does not exist"];
        }

        foreach ($productsData as $productData) {
            try {
                $product = $productModelClass::find($productData['product_id']);
                if (!$product) {
                    $errors[] = "Product {$productData['product_id']} not found";
                    continue;
                }

                $updateData = [
                    'is_ordered' => true,
                    'ordered_at' => \now(),
                    'ordered_by' => $userId,
                ];

                if (!empty($productData['rental_start_date']) && !empty($productData['rental_end_date'])) {
                    $updateData['is_rented'] = true;
                    $updateData['rented_at'] = \now();
                    $updateData['rental_start_date'] = $productData['rental_start_date'];
                    $updateData['rental_end_date'] = $productData['rental_end_date'];
                }

                $product->update($updateData);
                $updatedProducts[] = ['product_id' => $product->id, 'status' => 'updated'];
            } catch (\Exception $e) {
                $errors[] = "Failed to update product {$productData['product_id']}: {$e->getMessage()}";
            }
        }

        return [
            'success' => count($updatedProducts) > 0,
            'updated_count' => count($updatedProducts),
            'errors' => $errors,
            'data' => $updatedProducts
        ];
    }
}
