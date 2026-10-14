<?php

namespace App\Http\Controllers;

use App\Services\Contracts\CallbackApiKeyServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CallbackApiKeySettingsController extends Controller
{
    public function __construct(private readonly CallbackApiKeyServiceInterface $callbackApiKeys) {}

    public function index(): View
    {
        return view('settings.callback-api-key', [
            'isConfigured' => $this->callbackApiKeys->isConfigured(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $apiKey = $this->callbackApiKeys->generate();

        return redirect()
            ->route('settings.callback-api-key.index')
            ->with('status', 'Callback API key berhasil dibuat. Simpan key sekarang karena hanya ditampilkan satu kali.')
            ->with('callback_api_key', $apiKey);
    }
}
