<?php

namespace App\Services\Gateways;

use App\Services\Contracts\BankGatewayInterface;
use App\Services\MandiriH2hClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

class MandiriGateway implements BankGatewayInterface
{
    public function __construct(private readonly MandiriH2hClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function partnerServiceId(): string
    {
        return (string) config('services.mandiri_h2h.partner_service_id');
    }

    public function createVirtualAccountEndpoint(): string
    {
        return MandiriH2hClient::CREATE_VA_ENDPOINT;
    }

    public function updateVirtualAccountEndpoint(): string
    {
        return MandiriH2hClient::UPDATE_VA_ENDPOINT;
    }

    public function createVirtualAccount(array $payload): VirtualAccountGatewayResponse
    {
        return $this->gatewayResponse($this->client->createVirtualAccount($payload));
    }

    public function updateVirtualAccount(array $payload): VirtualAccountGatewayResponse
    {
        return $this->gatewayResponse($this->client->updateVirtualAccount($payload));
    }

    private function gatewayResponse(Response $response): VirtualAccountGatewayResponse
    {
        $responsePayload = $response->json();

        return new VirtualAccountGatewayResponse(
            status: $response->status(),
            successful: $response->successful(),
            serverError: $response->serverError(),
            payload: is_array($responsePayload) ? $responsePayload : ['body' => Str::limit($response->body(), 5000)],
        );
    }
}
