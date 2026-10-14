<?php

namespace App\Services\Contracts;

use App\Services\Gateways\VirtualAccountGatewayResponse;
use Illuminate\Http\Client\ConnectionException;

interface BankGatewayInterface
{
    public function isConfigured(): bool;

    public function partnerServiceId(): string;

    public function createVirtualAccountEndpoint(): string;

    public function updateVirtualAccountEndpoint(): string;

    /**
     * @param  array<string, string>  $payload
     *
     * @throws ConnectionException When the bank outcome cannot be determined.
     */
    public function createVirtualAccount(array $payload): VirtualAccountGatewayResponse;

    /**
     * @param  array<string, string>  $payload
     *
     * @throws ConnectionException When the bank outcome cannot be determined.
     */
    public function updateVirtualAccount(array $payload): VirtualAccountGatewayResponse;
}
