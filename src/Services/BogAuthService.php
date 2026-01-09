<?php

namespace Bog\Payment\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BogAuthService
{
    public function getAccessToken()
    {
        $url = \config('services.bog.auth_url');
        $clientId = \config('services.bog.client_id');
        $clientSecret = \config('services.bog.client_secret');

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post($url, [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->successful()) {
            $data = $response->json();

            return [
                'access_token' => $data['access_token'] ?? null,
                'token_type' => $data['token_type'] ?? 'Bearer',
                'expires_in' => $data['expires_in'] ?? null,
            ];
        }

        Log::error('BOG Auth error', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }
}
