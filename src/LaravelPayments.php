<?php

namespace EngAlalfy\LaravelPayments;

use EngAlalfy\LaravelPayments\Enums\GatewayType;
use EngAlalfy\LaravelPayments\Factories\PaymentGatewayFactory;
use EngAlalfy\LaravelPayments\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LaravelPayments
{
    protected PaymentGatewayInterface $gateway;

    /**
     * @param GatewayType $gatewayType The type of payment gateway to use
     * @param array|null $credential Optional credentials for the payment gateway if not present in config
     */
    public function __construct(protected GatewayType $gatewayType, array|null $credential = null)
    {
        // Get the appropriate gateway
        $this->gateway = PaymentGatewayFactory::create($gatewayType, $credential);
    }

    /**
     * Process a payment using the specified gateway
     *
     * @param  string  $orderId  Order identifier
     * @param  float  $amount  Payment amount
     * @param  array  $data  Additional data required for the payment
     * @return array Payment result including checkout URL
     */
    public function processPayment(string $orderId, float $amount, array $data): array
    {
        try {
            // Initialize payment
            $result = $this->gateway->initializePayment($orderId, $amount, $data);

            // Generate checkout URL
            $checkoutUrl = $this->gateway->getCheckoutUrl($result);

            return [
                'success' => true,
                'checkout_url' => $checkoutUrl,
                'gateway_response' => $result,
                'gateway' => $this->gatewayType,
            ];
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'gateway' => $this->gatewayType,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'gateway' => $this->gatewayType,
            ];
        }
    }

    /**
     * Verify a payment callback
     *
     * @param  Request  $request  The request containing callback data
     * @return array Verification result
     */
    public function verifyPayment(Request $request): array
    {
        try {
            return $this->gateway->verifyCallback($request);
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'gateway' => $this->gatewayType,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'gateway' => $this->gatewayType,
            ];
        }
    }

    /**
     * Get the gateway instance
     * @return \EngAlalfy\LaravelPayments\Interfaces\PaymentGatewayInterface
     */
    public function getGateway(): PaymentGatewayInterface
    {
        return $this->gateway;
    }
}
