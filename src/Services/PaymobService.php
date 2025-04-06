<?php

namespace EngAlalfy\LaravelPayments\Services;

use EngAlalfy\LaravelPayments\Interfaces\PaymentGatewayInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymobService implements PaymentGatewayInterface
{
    private string $baseUrl;
    private string $checkoutUrl;
    private string $publicKey;
    private string $hmacSecret;
    private array $config;

    public function __construct()
    {
        $this->baseUrl = config('payments.paymob.base_url', 'https://accept.paymob.com/v1');
        $this->checkoutUrl = config('payments.paymob.checkout_url', 'https://accept.paymob.com/unifiedcheckout');
        $this->publicKey = config('payments.paymob.public_key');
        $secretKey = config('payments.paymob.secret_key');
        $this->hmacSecret = config('payments.paymob.hmac_secret');
        $this->config = [
            'headers' => [
                'Authorization' => 'Token ' . $secretKey,
                'Content-Type' => 'application/json',
            ],
        ];
    }

    /**
     * Initialize a payment process by creating a payment intention.
     *
     * @param string $orderId The unique identifier for the order.
     * @param float $amount The total amount for the transaction.
     * @param array $data Additional data required for payment initialization.
     * @return array|string The response from the payment intention creation.
     * @throws RuntimeException If payment intention creation fails.
     */
    public function initializePayment(string $orderId, float $amount, array $data): array|string
    {
        // Extract necessary data for payment intention
        $methodId = $data['method_id'] ?? null;
        $items = $data['items'] ?? [];
        $billingData = $data['billing_data'] ?? [];
        $currency = $data['currency'] ?? 'EGP';
        $customer = $data['customer'] ?? null;
        $extras = $data['extras'] ?? null;
        $app = $data['app'] ?? false;

        // Validate required data
        if (!$methodId || empty($items) || empty($billingData)) {
            throw new RuntimeException('Missing required data for payment initialization');
        }

        // Create payment intention
        return $this->createPaymentIntention(
            $methodId,
            $orderId,
            $amount,
            $items,
            $billingData,
            $currency,
            $customer,
            $extras,
            $app
        );
    }

    /**
     * Generate the checkout URL for client-side redirection.
     *
     * @param mixed $data Client secret from payment intention response.
     * @return string The complete checkout URL.
     */
    public function getCheckoutUrl(mixed $data): string
    {
        return $this->checkoutUrl . '?' . http_build_query([
                'publicKey' => $this->publicKey,
                'clientSecret' => $data["client_secret"] ?? '',
            ]);
    }

    /**
     * Verify the callback of the payment by HMAC.
     *
     * @param Request $request The HTTP request containing the callback data.
     * @return array The result of the verification process.
     */
    public function verifyCallback(Request $request): array
    {
        try {
            if (!$this->hmacSecret) {
                throw new RuntimeException('HMAC secret is not configured');
            }

            $receivedHmac = $request->query('hmac');
            if (!$receivedHmac) {
                throw new RuntimeException('HMAC is missing from request');
            }

            $data = $request->all();
            if (empty($data)) {
                throw new RuntimeException('Request data is empty');
            }

            $orderedKeys = [
                'amount_cents',
                'created_at',
                'currency',
                'error_occured',
                'has_parent_transaction',
                'id',
                'integration_id',
                'is_3d_secure',
                'is_auth',
                'is_capture',
                'is_refunded',
                'is_standalone_payment',
                'is_voided',
                'order',
                'owner',
                'pending',
                'source_data_pan',
                'source_data_sub_type',
                'source_data_type',
                'success',
            ];

            $concatenatedString = '';
            foreach ($orderedKeys as $key) {
                $value = $data[$key] ?? '';
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $concatenatedString .= $value;
            }

            $calculatedHmac = hash_hmac('sha512', $concatenatedString, $this->hmacSecret);

            Log::debug('HMAC Verification Details', [
                'concatenated_string' => $concatenatedString,
                'received_hmac' => $receivedHmac,
                'calculated_hmac' => $calculatedHmac,
            ]);

            if (!hash_equals($calculatedHmac, $receivedHmac)) {
                throw new RuntimeException('HMAC verification failed');
            }

            return [
                'success' => true,
                'data' => $data,
                'message' => 'HMAC verification successful',
            ];

        } catch (Exception $e) {
            Log::error('Paymob verification failed', [
                'error' => $e->getMessage(),
                'data' => $data ?? null,
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
     * @param mixed $paymentMethod The payment method to evaluate.
     * @return float The price factor.
     */
    public function getPriceFactor(mixed $paymentMethod): float
    {
        return 1.0; // Default price factor
    }

    /**
     * Create a payment intention with structured data.
     *
     * @param mixed $methodId The payment method identifier.
     * @param mixed $id The unique identifier for the order.
     * @param float $amount Total amount of the transaction.
     * @param array $items Array of items with their details.
     * @param array $billingData Customer billing information.
     * @param string $currency Currency code (default: EGP).
     * @param array|null $customer Customer details (optional).
     * @param array|null $extras Additional data (optional).
     * @param bool $app Flag indicating if the request is from an app (optional).
     * @return array Response from Paymob.
     * @throws RuntimeException If payment intention creation fails.
     */
    private function createPaymentIntention(
        mixed  $methodId,
        mixed  $id,
        float  $amount,
        array  $items,
        array  $billingData,
        string $currency = 'EGP',
        ?array $customer = null,
        ?array $extras = null,
        bool   $app = false,
    ): array
    {
        $this->validateBillingData($billingData);
        $this->validateItems($items);

        try {
            $payload = [
                'amount' => $amount * 100,
                'currency' => $currency,
                'payment_methods' => [(int)$methodId],
                'items' => $items,
                'billing_data' => [
                    'apartment' => $billingData['apartment'],
                    'floor' => $billingData['floor'],
                    'first_name' => $billingData['first_name'],
                    'last_name' => $billingData['last_name'],
                    'street' => $billingData['street'],
                    'building' => $billingData['building'],
                    'phone_number' => $billingData['phone_number'],
                    'shipping_method' => $billingData['shipping_method'] ?? '',
                    'postal_code' => $billingData['postal_code'] ?? '',
                    'city' => $billingData['city'],
                    'country' => $billingData['country'],
                    'state' => $billingData['state'],
                    'email' => $billingData['email'],
                ],
                'customer' => $customer,
                'extras' => $extras,
                'redirection_url' => route('store.paymob.handle-callback', $app ? ['app' => true] : []),
                'special_reference' => $id,
            ];
            $response = Http::withHeaders($this->config['headers'])
                ->post($this->baseUrl . '/intention/', $payload);
            if (!$response->successful()) {
                throw new RuntimeException('Failed to create payment intention: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new RuntimeException('Error creating payment intention: ' . $e->getMessage());
        }
    }

    /**
     * Validate billing data structure.
     *
     * @param array $billingData The billing data to validate.
     * @throws RuntimeException If any required billing field is missing.
     */
    private function validateBillingData(array $billingData): void
    {
        $requiredFields = [
            'first_name',
            'last_name',
            'email',
            'phone_number',
            'street',
            'building',
            'apartment',
            'floor',
            'city',
            'state',
            'country',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($billingData[$field])) {
                throw new RuntimeException("Missing required billing field: {$field}");
            }
        }
    }

    /**
     * Validate items structure.
     *
     * @param array $items The items to validate.
     * @throws RuntimeException If any required item field is missing.
     */
    private function validateItems(array $items): void
    {
        if (empty($items)) {
            throw new RuntimeException('At least one item is required');
        }

        foreach ($items as $item) {
            $requiredFields = ['name', 'amount', 'description', 'quantity'];
            foreach ($requiredFields as $field) {
                if (!isset($item[$field])) {
                    throw new RuntimeException("Missing required item field: {$field}");
                }
            }
        }
    }
}
