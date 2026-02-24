<?php

namespace EngAlalfy\LaravelPayments\Factories;

use EngAlalfy\LaravelPayments\Enums\GatewayType;
use EngAlalfy\LaravelPayments\Interfaces\PaymentGatewayInterface;
use EngAlalfy\LaravelPayments\Services\KashierService;
use EngAlalfy\LaravelPayments\Services\PaymobService;
use EngAlalfy\LaravelPayments\Services\TelrService;
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
     * @param  array|null  $credential  Optional credentials for the payment gateway if not present in config
     * @return PaymentGatewayInterface
     *
     * @throws InvalidArgumentException If the gateway type is not supported
     */
    public static function create(GatewayType $gatewayType, array|null $credential = null): PaymentGatewayInterface
    {
        return match ($gatewayType) {
            GatewayType::PAYMOB => new PaymobService($credential),
            GatewayType::KASHIER => new KashierService($credential),
            GatewayType::TELR => new TelrService($credential),
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gatewayType->value}")
        };
    }
}
