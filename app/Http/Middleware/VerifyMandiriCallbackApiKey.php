<?php

namespace App\Http\Middleware;

use App\Models\Bank;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyMandiriCallbackApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $bank = Bank::query()->where('code', 'mandiri')->first();

        if ($bank === null || $bank->callback_api_key_hash === null) {
            Log::error('Mandiri callback rejected because its API key has not been configured.');

            return response()->json(['message' => 'Callback API key is not configured.'], 503);
        }

        $apiKey = $request->header('X-API-KEY');

        if (
            ! is_string($apiKey)
            || $apiKey === ''
            || ! hash_equals((string) $bank->callback_api_key_hash, hash('sha256', $apiKey))
        ) {
            return response()->json(['message' => 'Invalid callback API key.'], 401);
        }

        return $next($request);
    }
}
