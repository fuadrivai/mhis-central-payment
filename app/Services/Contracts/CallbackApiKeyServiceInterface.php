<?php

namespace App\Services\Contracts;

interface CallbackApiKeyServiceInterface
{
    public function isConfigured(): bool;

    /**
     * Generate and store a new callback key, returning the plaintext key once.
     */
    public function generate(): string;
}
