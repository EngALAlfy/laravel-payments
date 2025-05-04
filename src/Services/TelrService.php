<?php

namespace EngAlalfy\LaravelPayments\Services;

use Illuminate\Support\Facades\Http;

class TelrService
{
    protected $merchantId;
    protected $apiKey;
    protected $testMode;
    protected $apiUrl;
    protected $successUrl;
    protected $cancelUrl;
    protected $declineUrl;

    public function __construct(
        $merchantId,
        $apiKey,
        $testMode,
        $apiUrl,
        $successUrl,
        $cancelUrl,
        $declineUrl
    ) {
        $this->merchantId = $merchantId;
        $this->apiKey = $apiKey;
        $this->testMode = $testMode;
        $this->apiUrl = $apiUrl;
        $this->successUrl = $successUrl;
        $this->cancelUrl = $cancelUrl;
        $this->declineUrl = $declineUrl;
    }

    /**
     * Create a payment request and get redirect URL
     *
     * @param float $amount
     * @param string $currency
     * @param string $description
     * @param array $customerData
     * @return string|null
     */
    public function createPayment($amount, $currency, $description, $customerData = [])
    {
        $orderId = Str::random(16); // Generate a unique order ID

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

        if ($response->successful()) {
            $result = $response->json();

            // Check if the payment URL was generated successfully
            if (isset($result['order']) && isset($result['order']['url'])) {
                return [
                    'success' => true,
                    'redirect_url' => $result['order']['url'],
                    'order_id' => $orderId,
                    'reference' => $result['order']['ref'] ?? null
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Failed to create payment link',
            'error' => $response->json() ?? $response->body()
        ];
    }

    /**
     * Check the status of a payment
     *
     * @param string $reference
     * @return array
     */
    public function checkPaymentStatus($reference)
    {
        $checkData = [
            'ivp_method' => 'check',
            'ivp_store' => $this->merchantId,
            'ivp_authkey' => $this->apiKey,
            'order_ref' => $reference,
        ];

        $response = Http::post($this->apiUrl, $checkData);

        if ($response->successful()) {
            $result = $response->json();

            if (isset($result['order'])) {
                return [
                    'success' => true,
                    'data' => $result['order'],
                    'status' => $result['order']['status']['code'] ?? null,
                    'is_paid' => ($result['order']['status']['code'] ?? '') === 'A',
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Failed to retrieve payment status',
            'error' => $response->json() ?? $response->body()
        ];
    }
}
