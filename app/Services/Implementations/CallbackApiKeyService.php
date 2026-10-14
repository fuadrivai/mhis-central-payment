<?php

namespace App\Services\Implementations;

use App\Models\Bank;
use App\Services\Contracts\CallbackApiKeyServiceInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CallbackApiKeyService implements CallbackApiKeyServiceInterface
{
    public function isConfigured(): bool
    {
        return Bank::query()
            ->where('code', 'mandiri')
            ->whereNotNull('callback_api_key_hash')
            ->exists();
    }

    public function generate(): string
    {
        $apiKey = bin2hex(random_bytes(32));

        DB::transaction(function () use ($apiKey): void {
            $bank = Bank::query()
                ->where('code', 'mandiri')
                ->lockForUpdate()
                ->first();

            if ($bank === null) {
                throw new ServiceUnavailableHttpException(
                    null,
                    'Data konfigurasi Bank Mandiri belum tersedia.',
                );
            }

            $bank->forceFill(['callback_api_key_hash' => hash('sha256', $apiKey)])->save();
        });

        return $apiKey;
    }
}
