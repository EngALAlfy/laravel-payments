<?php

namespace EngAlalfy\LaravelPayments;

use EngAlalfy\LaravelPayments\Enums\GatewayType;
use EngAlalfy\LaravelPayments\Factories\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LaravelPayments
{
    /**
     * Process a payment using the specified gateway
     *
     * @param  GatewayType  $gatewayType  The type of payment gateway to use
     * @param  string  $orderId  Order identifier
     * @param  float  $amount  Payment amount
     * @param  array  $data  Additional data required for the payment
     * @return array Payment result including checkout URL
     */
    public function processPayment(GatewayType $gatewayType, string $orderId, float $amount, array $data): array
    {
        try {
            // Get the appropriate gateway
            $gateway = PaymentGatewayFactory::create($gatewayType);

            // Initialize payment
            $result = $gateway->initializePayment($orderId, $amount, $data);

            // Generate checkout URL
            $checkoutUrl = $gateway->getCheckoutUrl($result);

            return [
                'success' => true,
                'checkout_url' => $checkoutUrl,
                'gateway_response' => $result,
                'gateway' => $gatewayType,
            ];
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'gateway' => $gatewayType,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'gateway' => $gatewayType,
            ];
        }
    }

    /**
     * Verify a payment callback
     *
     * @param  GatewayType  $gatewayType  The type of payment gateway
     * @param  Request  $request  The request containing callback data
     * @return array Verification result
     */
    public function verifyPayment(GatewayType $gatewayType, Request $request): array
    {
        try {
            return PaymentGatewayFactory::create($gatewayType)->verifyCallback($request);
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'gateway' => $gatewayType,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'gateway' => $gatewayType,
            ];
        }
    }
}
