<?php

namespace Bog\Payment\Services;

use Bog\Payment\Support\BogConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BogAuthService
{
    public function getAccessToken()
    {
        $url = BogConfig::authUrl();
        $clientId = BogConfig::get('client_id');
        $clientSecret = BogConfig::get('client_secret');

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout(15)
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
