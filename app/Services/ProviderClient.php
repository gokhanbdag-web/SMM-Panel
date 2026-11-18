<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

/**
 * Generic SMM provider API client (the common action=add/status/balance protocol).
 * Credentials are read from the database-backed provider record, never from source code.
 */
final class ProviderClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout = 25
    ) {
        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($this->baseUrl, PHP_URL_SCHEME), ['https'], true)) {
            throw new RuntimeException('Sağlayıcı API adresi HTTPS olmalıdır.');
        }
        if ($this->apiKey === '') {
            throw new RuntimeException('Sağlayıcı API anahtarı tanımlanmamış.');
        }
    }

    private function request(array $payload): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL eklentisi gerekli.');
        $payload['key'] = $this->apiKey;
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => max(5, min(60, $this->timeout)),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) throw new RuntimeException('Sağlayıcı bağlantı hatası: '.$error);
        if ($status < 200 || $status >= 300) throw new RuntimeException('Sağlayıcı HTTP hatası: '.$status);
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) throw new RuntimeException('Sağlayıcı geçersiz JSON yanıtı döndürdü.');
        if (isset($data['error']) && $data['error'] !== '') throw new RuntimeException('Sağlayıcı hatası: '.(string)$data['error']);
        return $data;
    }

    public function add(int $serviceId, string $link, int $quantity): string
    {
        $result = $this->request(['action'=>'add','service'=>$serviceId,'link'=>$link,'quantity'=>$quantity]);
        $id = $result['order'] ?? null;
        if (!is_scalar($id) || (string)$id === '') throw new RuntimeException('Sağlayıcı sipariş numarası döndürmedi.');
        return (string)$id;
    }

    public function status(string $providerOrderId): array
    {
        $result = $this->request(['action'=>'status','order'=>$providerOrderId]);
        return [
            'status' => strtolower((string)($result['status'] ?? 'unknown')),
            'charge' => isset($result['charge']) ? (float)$result['charge'] : null,
            'start_count' => isset($result['start_count']) ? (int)$result['start_count'] : null,
            'remains' => isset($result['remains']) ? (int)$result['remains'] : null,
        ];
    }

    public function balance(): array
    {
        $result = $this->request(['action'=>'balance']);
        return ['balance'=>(float)($result['balance']??0),'currency'=>(string)($result['currency']??'')];
    }
}
