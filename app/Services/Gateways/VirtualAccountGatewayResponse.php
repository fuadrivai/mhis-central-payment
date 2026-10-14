<?php

namespace App\Services\Gateways;

final readonly class VirtualAccountGatewayResponse
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $status,
        public bool $successful,
        public bool $serverError,
        public array $payload,
    ) {}
}
