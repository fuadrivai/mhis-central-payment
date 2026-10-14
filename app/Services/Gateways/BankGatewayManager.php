<?php

namespace App\Services\Gateways;

use App\Models\Bank;
use App\Services\Contracts\BankGatewayInterface;
use InvalidArgumentException;

class BankGatewayManager
{
    /**
     * Register a new bank integration by mapping its `banks.driver` value to a gateway class.
     *
     * @var array<string, class-string<BankGatewayInterface>>
     */
    private const DRIVERS = [
        'mandiri' => MandiriGateway::class,
    ];

    public function for(Bank $bank): BankGatewayInterface
    {
        $gateway = self::DRIVERS[$bank->driver] ?? null;

        if ($gateway === null) {
            throw new InvalidArgumentException("Driver bank [{$bank->driver}] belum didukung.");
        }

        return app($gateway);
    }
}
