<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MandiriH2hClient
{
    public const CREATE_VA_ENDPOINT = '/api/internal/va/create';

    public const UPDATE_VA_ENDPOINT = '/api/internal/va/update';

    public function isConfigured(): bool
    {
        return filled(config('services.mandiri_h2h.base_url'))
            && filled(config('services.mandiri_h2h.api_key'))
            && filled(config('services.mandiri_h2h.partner_service_id'));
    }

    /**
     * @param  array<string, string>  $payload
     */
    public function createVirtualAccount(array $payload): Response
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Konfigurasi koneksi Mandiri H2H belum lengkap.');
        }

        return $this->sendVirtualAccountRequest(self::CREATE_VA_ENDPOINT, $payload, 'POST');
    }

    /**
     * @param  array<string, string>  $payload
     */
    public function updateVirtualAccount(array $payload): Response
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Konfigurasi koneksi Mandiri H2H belum lengkap.');
        }

        return $this->sendVirtualAccountRequest(self::UPDATE_VA_ENDPOINT, $payload, 'PUT');
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function sendVirtualAccountRequest(string $endpoint, array $payload, string $method): Response
    {
        $baseUrl = rtrim((string) config('services.mandiri_h2h.base_url'), '/');

        return Http::acceptJson()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-API-KEY' => (string) config('services.mandiri_h2h.api_key'),
            ])
            ->connectTimeout((int) config('services.mandiri_h2h.connect_timeout', 3))
            ->timeout((int) config('services.mandiri_h2h.timeout', 15))
            ->send($method, $baseUrl.$endpoint, ['json' => $payload]);
    }
}
