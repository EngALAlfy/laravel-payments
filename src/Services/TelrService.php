<?php

namespace EngAlalfy\LaravelPayments\Services;

use EngAlalfy\LaravelPayments\Interfaces\PaymentGatewayInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class TelrService implements PaymentGatewayInterface
{
    private string $merchantId;
    private string $apiKey;
    private bool $testMode;
    private string $apiUrl;
    private string $successUrl;
    private string $cancelUrl;
    private string $declineUrl;

    public function __construct()
    {
        $this->merchantId = config('payments.telr.merchant_id', '');
        $this->apiKey = config('payments.telr.api_key', '');
        $this->testMode = config('payments.telr.test_mode', false);
        $this->apiUrl = config('payments.telr.api_url', 'https://secure.telr.com/gateway/order.json');
        $this->successUrl = config('payments.telr.success_url', '');
        $this->cancelUrl = config('payments.telr.cancel_url', '');
        $this->declineUrl = config('payments.telr.decline_url', '');
    }

    /**
     * Initialize a payment process by creating a payment request.
     *
     * @param string $orderId The unique identifier for the order.
     * @param float $amount The total amount for the transaction.
     * @param array $data Additional data required for payment initialization.
     * @return array|string The response from the payment creation.
     *
     */
    public function initializePayment(string $orderId, float $amount, array $data): array|string
    {
        $currency = $data['currency'] ?? 'USD';
        $description = $data['description'] ?? 'Payment for order '.$orderId;
        $customerData = $data['customer_data'] ?? [];

        if (empty($orderId) || $amount <= 0) {
            throw new RuntimeException('Invalid order ID or amount for payment initialization');
        }

        return $this->createPaymentRequest($orderId, $amount, $currency, $description, $customerData);
    }

    /**
     * Get the checkout URL for client-side redirection.
     *
     * @param  mixed  $data  Data required to generate the URL.
     * @return string The checkout URL.
     */
    public function getCheckoutUrl(mixed $data): string
    {
        if (is_array($data) && isset($data['redirect_url'])) {
            return $data['redirect_url'];
        }

        throw new RuntimeException('Invalid payment data: redirect URL not found');
    }

    /**
     * Verify the callback from Telr to ensure it is valid.
     *
     * @param Request $request The HTTP request containing the callback data.
     * @return array|bool The result of the verification process.
     */
    public function verifyCallback(Request $request): array|bool
    {
        try {
            $reference = $request->input('order_ref');

            if (empty($reference)) {
                throw new RuntimeException('Order reference is missing from request');
            }

            $result = $this->checkPaymentStatus($reference);

            if (!$result['success']) {
                throw new RuntimeException('Failed to verify payment: ' . ($result['message'] ?? 'Unknown error'));
            }

            return [
                'success' => true,
                'data' => $result['data'] ?? [],
                'is_paid' => $result['is_paid'] ?? false,
                'message' => 'Payment verification successful'
            ];

        } catch (Exception $e) {
            Log::error('Telr verification failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Get the price factor based on the payment method.
     *
     * @param  mixed  $paymentMethod  The payment method to evaluate.
     * @return float The price factor.
     */
    public function getPriceFactor(mixed $paymentMethod): float
    {
        return 1.0; // Default price factor
    }

    /**
     * Create a payment request with Telr.
     *
     * @param  string  $orderId  The unique identifier for the order.
     * @param  float  $amount  The total amount for the transaction.
     * @param  string  $currency  Currency code.
     * @param  string  $description  Description of the payment.
     * @param  array  $customerData  Customer information.
     * @return array Response containing payment details and redirect URL.
     *
     * @throws RuntimeException If payment creation fails.
     */
    private function createPaymentRequest(
        string $orderId,
        float $amount,
        string $currency,
        string $description,
        array $customerData = []
    ): array {
        try {
            $paymentData = [
                'ivp_method' => 'create',
                'ivp_store' => $this->merchantId,
                'ivp_authkey' => $this->apiKey,
                'ivp_cart' => $orderId,
                'ivp_test' => $this->testMode ? '1' : '0',
                'ivp_amount' => $amount,
                'ivp_currency' => $currency,
                'ivp_desc' => $description,
                'return_auth' => $this->successUrl,
                'return_can' => $this->cancelUrl,
                'return_decl' => $this->declineUrl,
            ];

            // Add customer data if provided
            if (!empty($customerData['name'])) {
                $paymentData['bill_fname'] = $customerData['name'];
            }

            if (!empty($customerData['email'])) {
                $paymentData['bill_email'] = $customerData['email'];
            }

            if (!empty($customerData['phone'])) {
                $paymentData['bill_tel'] = $customerData['phone'];
            }

            // Make API request to Telr
            $response = Http::post($this->apiUrl, $paymentData);

            if (!$response->successful()) {
                throw new RuntimeException('Failed to create payment request: ' . $response->body());
            }

            $result = $response->json();

            // Check if the payment URL was generated successfully
            if (!isset($result['order']) || !isset($result['order']['url'])) {
                throw new RuntimeException('Invalid response from payment gateway');
            }

            return [
                'success' => true,
                'redirect_url' => $result['order']['url'],
                'order_id' => $orderId,
                'reference' => $result['order']['ref'] ?? null
            ];

        } catch (Exception $e) {
            Log::error('Telr payment creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Error creating payment: ' . $e->getMessage());
        }
    }

    /**
     * Check the status of a payment with Telr.
     *
     * @param  string  $reference  The reference ID of the order.
     * @return array Response containing payment status details.
     */
    private function checkPaymentStatus(string $reference): array
    {
        try {
            $checkData = [
                'ivp_method' => 'check',
                'ivp_store' => $this->merchantId,
                'ivp_authkey' => $this->apiKey,
                'order_ref' => $reference,
            ];

            $response = Http::post($this->apiUrl, $checkData);

            if (!$response->successful()) {
                throw new RuntimeException('Failed to check payment status: ' . $response->body());
            }

            $result = $response->json();

            if (!isset($result['order'])) {
                throw new RuntimeException('Invalid response from payment gateway');
            }

            return [
                'success' => true,
                'data' => $result['order'],
                'status' => $result['order']['status']['code'] ?? null,
                'is_paid' => ($result['order']['status']['code'] ?? '') === 'A',
            ];

        } catch (Exception $e) {
            Log::error('Telr payment status check failed', [
                'error' => $e->getMessage(),
                'reference' => $reference,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve payment status: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
}
