<?php

namespace EngAlalfy\LaravelPayments\Factories;

use EngAlalfy\LaravelPayments\Enums\GatewayType;
use EngAlalfy\LaravelPayments\Interfaces\PaymentGatewayInterface;
use EngAlalfy\LaravelPayments\Services\KashierService;
use EngAlalfy\LaravelPayments\Services\PaymobService;
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
     * @param  GatewayType  $gatewayType  The type of payment gateway
     *
     * @throws InvalidArgumentException If the gateway type is not supported
     */
    public static function create(GatewayType $gatewayType): PaymentGatewayInterface
    {
        return match ($gatewayType) {
            GatewayType::PAYMOB => new PaymobService,
            GatewayType::KASHIER => new KashierService,
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gatewayType->value}")
        };
    }
}
