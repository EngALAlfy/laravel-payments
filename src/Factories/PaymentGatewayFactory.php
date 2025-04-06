<?php

namespace EngAlalfy\LaravelPayments\Factories;

use EngAlalfy\LaravelPayments\Interfaces\PaymentGatewayInterface;
use InvalidArgumentException;

/**
 * Payment Gateway Factory
 * Creates the appropriate payment gateway based on the gateway type
 */
class PaymentGatewayFactory
{
    /**
     * Create a payment gateway instance
     *
     * @param string $gatewayType The type of payment gateway
     * @return PaymentGatewayInterface
     * @throws InvalidArgumentException If the gateway type is not supported
     */
    public static function create(string $gatewayType): PaymentGatewayInterface
    {
        return match ($gatewayType) {
            'paymob' => new PaymobGateway(),
            'kashier' => new KashierGateway(),
            // Add new gateways here without modifying existing code
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gatewayType}")
        };
    }
}
