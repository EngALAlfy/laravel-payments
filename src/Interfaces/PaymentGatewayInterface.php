<?php

namespace EngAlalfy\LaravelPayments\Interfaces;

use Illuminate\Http\Request;

/**
 * Payment Gateway Interface
 * All payment gateway classes must implement this interface
 */
interface PaymentGatewayInterface
{
    /**
     * Initialize a payment
     *
     * @param string $orderId
     * @param float $amount
     * @param array $data Additional data required for the payment
     * @return array|string Response from the payment gateway
     */
    public function initializePayment(string $orderId, float $amount, array $data): array|string;

    /**
     * Get the checkout URL for client-side redirection
     *
     * @param mixed $data Data required to generate the URL
     * @return string The checkout URL
     */
    public function getCheckoutUrl(mixed $data): string;

    /**
     * Verify payment callback
     *
     * @param Request $request
     * @return array
     */
    public function verifyCallback(Request $request): array;

    /**
     * Get price factor based on payment method
     *
     * @param mixed $paymentMethod
     * @return float
     */
    public function getPriceFactor(mixed $paymentMethod): float;
}
