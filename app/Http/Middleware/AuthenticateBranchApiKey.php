<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBranchApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = (string) $request->header('X-API-KEY');

        $branch = $apiKey === ''
            ? null
            : Branch::query()->active()->where('api_key', $apiKey)->first();

        if (! $branch) {
            return response()->json(['message' => 'API key tidak valid atau cabang nonaktif.'], 401);
        }

        $request->attributes->set('branch', $branch);

        return $next($request);
    }
}
