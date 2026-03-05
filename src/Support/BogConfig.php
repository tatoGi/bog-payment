<?php

namespace Bog\Payment\Support;

class BogConfig
{
    public static function get(string $key, $default = null)
    {
        $modernValue = \config("bog-payment.{$key}");
        if ($modernValue !== null) {
            return $modernValue;
        }

        $legacyValue = \config("services.bog.{$key}");
        if ($legacyValue !== null) {
            return $legacyValue;
        }

        return $default;
    }

    public static function authUrl(): string
    {
        return (string) self::get(
            'auth_url',
            'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token'
        );
    }

    public static function apiBaseUrl(): string
    {
        return \rtrim((string) self::get('api_base_url', 'https://ipay.ge/opay/api/v1'), '/');
    }

    public static function ordersUrl(): string
    {
        return (string) self::get('orders_url', self::apiBaseUrl() . '/checkout/orders');
    }

    public static function paymentDetailsUrl(string $orderId): string
    {
        $base = \rtrim((string) self::get('payment_details_url', self::apiBaseUrl() . '/checkout/payment'), '/');

        return "{$base}/{$orderId}";
    }
}
