<?php

namespace Bog\Payment\Services;

use Bog\Payment\Models\BogPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    public function payWithSavedCard(string $accessToken, string $parentOrderId, array $paymentData): array
    {
        $endpoint = "/v1/orders/{$parentOrderId}/payments";

        $payload = [
            'callback_url' => $paymentData['callback_url'],
            'amount' => $paymentData['amount'],
            'basket' => $paymentData['basket'],
            'language' => $paymentData['language'] ?? 'ka',
        ];

        $response = $this->makeRequest('POST', $endpoint, $accessToken, $payload);

        if (isset($response['error'])) {
            Log::error('BOG Payment with saved card failed', [
                'error' => $response['error'],
                'parent_order_id' => $parentOrderId,
                'response' => $response,
            ]);

            return [
                'success' => false,
                'message' => $response['error']['message'] ?? 'Payment with saved card failed',
                'status' => $this->getLastHttpStatus() ?? 400,
            ];
        }

        return [
            'success' => true,
            'data' => $response,
            'message' => 'Payment with saved card initiated successfully',
        ];
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
            $baseUrl = config('services.bog.api_base_url', 'https://api.bog.ge/payments');

            // Build full URL (handle both absolute and relative URLs)
            $fullUrl = str_starts_with($url, 'http') ? $url : $baseUrl . $url;

            $http = Http::withToken($accessToken)
                ->withHeaders($headers)
                ->withOptions(options: ['debug' => config('app.debug')])
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
            $baseUrl = config('services.bog.api_base_url', 'https://api.bog.ge/payments');
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

            // Create a database record for the payment
            $bogPayment = new \App\Models\BogPayment([
                'bog_order_id' => $tempOrderId,
                'external_order_id' => $payload['external_order_id'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
                'amount' => $payload['purchase_units'][0]['amount']['value'] ?? 0,
                'currency' => $payload['purchase_units'][0]['amount']['currency_code'] ?? 'GEL',
                'status' => 'pending',
                'request_payload' => $payload,
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

            // Save the payment record
            if (! $bogPayment->save()) {
                $error = 'Failed to save payment to database';
                Log::error($error);
                throw new \Exception($error);
            }

            // Transform the payload to match BOG API expectations
            $transformedPayload = $this->transformPayloadForBogApi($payload);

            // Make the API request
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ])
                ->withOptions([
                    'debug' => config('app.debug') ? fopen('php://stderr', 'w') : false,
                    'verify' => config('app.env') === 'production',
                ])
                ->timeout(30)
                ->post(config('services.bog.orders_url', 'https://api.bog.ge/payments/v1/ecommerce/orders'), $transformedPayload);

            $responseBody = $response->json() ?? $response->body();
            $this->lastHttpStatus = $response->status();

            if (! $response->successful()) {
                $error = $responseBody['error_description'] ?? $responseBody['message'] ?? 'Unknown error';
                Log::error('BOG API - Request failed', [
                    'status_code' => $this->lastHttpStatus,
                    'error' => $error,
                    'response' => $responseBody,
                ]);
                throw new \Exception($error);
            }

            // Update the payment record with the BOG order ID
            $bogPayment->update([
                'bog_order_id' => $responseBody['id'] ?? $tempOrderId,
                'response_data' => $responseBody,
                'redirect_url' => $responseBody['_links']['redirect']['href'] ?? null,
                'status' => 'created',
            ]);

            return $responseBody;
        } catch (\Exception $e) {
            Log::error('BOG Payment - Error in createOrder', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update the payment status to failed if we have a record
            if (isset($bogPayment) && $bogPayment instanceof \App\Models\BogPayment) {
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
     * Transform payload to match BOG API expectations
     */
    /**s
     * Transform payload to match BOG API expectations
     */
    private function transformPayloadForBogApi(array $payload): array
    {
        // If purchase_units is an array with numeric keys (multiple purchase units)
        if (isset($payload['purchase_units']) && is_array($payload['purchase_units'])) {
            $purchaseUnits = $payload['purchase_units'];

            // Check if it's a sequential array (indexed with numbers)
            if (array_keys($purchaseUnits) === range(0, count($purchaseUnits) - 1)) {
                // Handle multiple purchase units (take the first one)
                if (isset($purchaseUnits[0]['amount'], $purchaseUnits[0]['items'])) {
                    $firstUnit = $purchaseUnits[0];

                    $transformedPayload = $payload;
                    $transformedPayload['purchase_units'] = [
                        'total_amount' => $firstUnit['amount']['value'] ?? 0,
                        'currency' => $firstUnit['amount']['currency_code'] ?? 'GEL',
                        'basket' => array_map(function ($item) {
                            return [
                                'product_id' => $item['sku'] ?? $item['product_id'] ?? uniqid(),
                                'name' => $item['name'] ?? 'Product',
                                'quantity' => $item['quantity'] ?? 1,
                                'unit_price' => $item['unit_price'] ?? 0,
                                'total_amount' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
                            ];
                        }, $firstUnit['items'] ?? []),
                    ];

                    return $transformedPayload;
                }
            }
            // Handle single purchase unit with basket
            elseif (isset($purchaseUnits['basket']) && is_array($purchaseUnits['basket'])) {
                $transformedPayload = $payload;
                $transformedPayload['purchase_units'] = [
                    'total_amount' => $purchaseUnits['total_amount'] ?? $purchaseUnits['amount']['value'] ?? 0,
                    'currency' => $purchaseUnits['currency'] ?? $purchaseUnits['amount']['currency_code'] ?? 'GEL',
                    'basket' => array_map(function ($item) {
                        return [
                            'product_id' => $item['product_id'] ?? $item['sku'] ?? uniqid(),
                            'name' => $item['name'] ?? 'Product',
                            'quantity' => $item['quantity'] ?? 1,
                            'unit_price' => $item['unit_price'] ?? 0,
                            'total_amount' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
                        ];
                    }, $purchaseUnits['basket']),
                ];

                return $transformedPayload;
            }
        }

        // If no transformation needed, return original payload

        return $payload;
    }

    /**
     * Get order details from BOG API
     */
    public function getOrderDetails(string $accessToken, string $orderId): ?array
    {
        $url = config('services.bog.orders_url', 'https://api.bog.ge/payments/v1/ecommerce/orders') . '/' . $orderId;

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
        $url = config('services.bog.orders_url', 'https://api.bog.ge/payments/v1/ecommerce/orders') . "/{$parentOrderId}/payments";

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
        $url = config('services.bog.orders_url', 'https://api.bog.ge/payments/v1/ecommerce/orders') . "/{$orderId}/save-card";

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
        $endpoint = config('services.bog.api_url') . '/v1/orders/' . $orderId . '/preauthorization/reject';

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
        $endpoint = config('services.bog.api_url') . '/v1/orders/' . $orderId . '/preauthorization/confirm';

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
        $endpoint = config('services.bog.api_url') . "/v1/orders/{$orderId}/saved-card";

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
        $url = config('services.bog.orders_url', 'https://api.bog.ge/payments/v1/ecommerce/orders') . "/{$orderId}/save-card";

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
        $url = config('services.bog.orders_url', 'https://api.bog.ge/payments/v1/ecommerce/orders') . "/{$parentOrderId}/payments";

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
}