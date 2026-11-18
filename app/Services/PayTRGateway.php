<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

/**
 * PayTR Direct API token and callback signature helpers.
 * Merchant credentials are stored in settings and are never committed to Git.
 */
final class PayTRGateway
{
    public static function createToken(array $data, string $merchantId, string $key, string $salt): string
    {
        foreach (['email','payment_amount','merchant_oid','user_ip','user_name','user_address','user_phone','basket'] as $field) {
            if (!array_key_exists($field, $data)) throw new RuntimeException('PayTR alanı eksik: '.$field);
        }
        $basket = base64_encode(json_encode($data['basket'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $hashStr = $merchantId.$data['user_ip'].$data['merchant_oid'].$data['email'].$data['payment_amount'].$basket.($data['no_installment']??0).($data['max_installment']??0).($data['currency']??'TL').($data['test_mode']??0);
        return base64_encode(hash_hmac('sha256', $hashStr.$salt, $key, true));
    }

    public static function verifyCallback(string $merchantOid, string $status, string $totalAmount, string $hash, string $key, string $salt): bool
    {
        $expected = base64_encode(hash_hmac('sha256', $merchantOid.$salt.$status.$totalAmount, $key, true));
        return hash_equals($expected, $hash);
    }

    public static function callbackResponse(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'OK';
        exit;
    }
}
