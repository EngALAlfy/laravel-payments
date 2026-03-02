<?php

namespace EngAlalfy\LaravelPayments\Services;

use EngAlalfy\LaravelPayments\Interfaces\PaymentGatewayInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FawaterakService implements PaymentGatewayInterface
{
    private const TEST_API_URL = 'https://staging.fawaterk.com/api/v2/';
    private const PROD_API_URL = 'https://staging.fawaterk.com/api/v2/';

    private string $apiUrl;
    private string $token;

    public function __construct(?array $credential = null)
    {
        $isTest = data_get($credential, "is_test", false);
        $apiUrl = data_get($credential, "api_url", $isTest ? self::TEST_API_URL : self::PROD_API_URL);
        $token = data_get($credential, "token");
        if ($apiUrl && $token) {
            $this->apiUrl = $apiUrl;
            $this->token = $token;
        } else {
            $this->apiUrl = config('payments.fawaterak.api_url', self::TEST_API_URL);
            $this->token = config('payments.fawaterak.token', '');
        }
    }

    /**
     * @param $apiUrl
     * @param $token
     * @return FawaterakService
     * @deprecated Use constructor or set credentials in config/payments.php instead
     */
    public function credentials($apiUrl = null, $token = null): FawaterakService
    {
        if ($apiUrl) {
            $this->apiUrl = $apiUrl;
        }

        if ($token) {
            $this->token = $token;
        }

        return $this;
    }

    /**
     * Fetch available payment methods from Fawaterak.
     *
     * @param string $orderId
     * @param float $amount
     * @param array $data
     * @return array|string
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function initializePayment(string $orderId, float $amount, array $data): array|string
    {
        try {
            // Validate required fields
            $required = [
                'payment_method_id',
                'cartTotal',
                'currency',
                'invoice_number',
                'customerData',
                'cartItems',
                'redirectionUrls'
            ];

            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new RuntimeException("Missing required field: $field");
                }
            }

            // Call createInvoice() from same service
            $result = $this->createInvoice(
                paymentMethodId: $data['payment_method_id'],
                cartTotal: $data['cartTotal'],
                currency: $data['currency'],
                invoiceNumber: $data['invoice_number'],
                customerData: $data['customerData'],
                cartItems: $data['cartItems'],
                redirectionUrls: $data['redirectionUrls'],
                options: $data['options'] ?? []
            );

            if (!$result['success']) {
                throw new RuntimeException('Failed to create invoice: ' . ($response['message'] ?? 'Unknown error'));
            }

            $paymentData = data_get($result, 'data');

            return [
                'success' => true,
                'data' => $paymentData,
                'raw' => data_get($result, 'raw')
            ];
        } catch (Exception $e) {
            Log::error('Fawaterak initializePayment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute payment & return checkout URL or payment code.
     *
     * @param mixed $data Array with required fields.
     * @return string Checkout URL or JSON-encoded payment data (for codes)
     * @throws \Exception
     */
    public function getCheckoutUrl(mixed $data): string
    {
        try {
            /**
             * Logic:
             * - if redirectTo present → return URL (Visa/Mastercard)
             * - else return JSON-encoded codes (e.g., fawryCode, amanCode etc.)
             */

            $data = data_get($data, 'data', []);
            if (!array_key_exists('payment_data', $data)) {
                throw new RuntimeException('Missing payment_data in response');
            }

            $paymentData = data_get($data, 'payment_data', []);
            if (isset($paymentData['redirectTo'])) {
                return $paymentData['redirectTo'];
            }

            // fallback: return payment code as JSON
            return json_encode($paymentData);
        } catch (Exception $e) {
            Log::error('Fawaterak getCheckoutUrl failed', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Create an invoice / execute payment request to Fawaterak.
     *
     * @param int $paymentMethodId (from getPaymentMethods)
     * @param float $cartTotal
     * @param string $currency
     * @param string $invoiceNumber
     * @param array $customerData ['first_name', 'last_name', 'email', 'phone', 'address']
     * @param array $cartItems each item: ['name', 'price', 'quantity']
     * @param array $redirectionUrls ['successUrl', 'failUrl', 'pendingUrl']
     * @param array $options optional extra fields: frequency, discountData, taxData, etc.
     * @return array
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    private function createInvoice(
        int    $paymentMethodId,
        float  $cartTotal,
        string $currency,
        string $invoiceNumber,
        array  $customerData,
        array  $cartItems,
        array  $redirectionUrls,
        array  $options = []
    ): array {
        try {
            $payload = [
                'payment_method_id' => $paymentMethodId,
                'cartTotal' => (string)$cartTotal,
                'currency' => $currency,
                'invoice_number' => $invoiceNumber,
                'customer' => [
                    'first_name' => $customerData['first_name'] ?? '',
                    'last_name' => $customerData['last_name'] ?? '',
                    'email' => $customerData['email'] ?? '',
                    'phone' => $customerData['phone'] ?? '',
                    'address' => $customerData['address'] ?? '',
                ],
                'redirectionUrls' => [
                    'successUrl' => $redirectionUrls['successUrl'] ?? '',
                    'failUrl' => $redirectionUrls['failUrl'] ?? '',
                    'pendingUrl' => $redirectionUrls['pendingUrl'] ?? '',
                ],
                'cartItems' => $cartItems,
            ];

            // Add any optional fields if provided
            $optionalFields = [
                'frequency',
                'customExpireDate',
                'discountData',
                'taxData',
                'authAndCapture',
                'payLoad',
                'mobileWalletNumber',
                'due_date',
                'sendEmail',
                'sendSMS',
                'lang',
                'redirectOption',
            ];

            foreach ($optionalFields as $field) {
                if (array_key_exists($field, $options)) {
                    $payload[$field] = $options[$field];
                }
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ])->post($this->apiUrl . 'invoiceInitPay', $payload);

            if (!$response->successful()) {
                throw new RuntimeException('Failed to create invoice: ' . $response->body());
            }

            $result = $response->json();

            return [
                'success' => $result['status'] === 'success',
                'data' => $result['data'] ?? [],
                'raw' => $result,
            ];
        } catch (Exception $e) {
            Log::error('Fawaterak createInvoice failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Verify callback by fetching invoice data from Fawaterak API
     * and validating that the invoice is paid and the total matches.
     *
     * @param mixed $data  Callback data containing 'invoice_id' and 'total'
     * @return array|bool
     * @throws \Exception
     */
    public function verifyCallback(mixed $data): array|bool
    {
        try {
            Log::info('Fawaterak callback received', $data);

            $invoiceId = data_get($data, 'invoice_id');
            $expectedTotal = data_get($data, 'total');

            if (empty($invoiceId)) {
                throw new RuntimeException('Missing invoice_id in callback data');
            }

            // Fetch invoice data from Fawaterak API
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ])->get($this->apiUrl . 'getInvoiceData/' . $invoiceId);

            if (!$response->successful()) {
                throw new RuntimeException('Failed to fetch invoice data from Fawaterak: ' . $response->body());
            }

            $result = $response->json();

            // Validate API response status
            if (data_get($result, 'status') !== 'success') {
                throw new RuntimeException('Invoice data request was not successful for invoice: ' . $invoiceId);
            }

            $invoiceData = data_get($result, 'data', []);

            // Check that the invoice is paid
            $isPaid = (int) data_get($invoiceData, 'paid') === 1;

            if (!$isPaid) {
                throw new RuntimeException('Invoice is not paid: ' . $invoiceId);
            }

            // Validate the total matches (prevent tampering)
            $apiTotal = (float) data_get($invoiceData, 'total');

            if ($expectedTotal !== null && (float) $expectedTotal !== $apiTotal) {
                throw new RuntimeException('Total amount mismatch for invoice ' . $invoiceId . ': expected ' . $expectedTotal . ', got ' . $apiTotal);
            }

            Log::info('Fawaterak callback verified successfully', [
                'invoice_id' => $invoiceId,
                'total' => $apiTotal,
                'data' => $invoiceData,
            ]);

            return [
                'success' => true,
                'message' => 'Payment verified successfully',
                'data' => $invoiceData,
            ];
        } catch (Exception $e) {
            Log::error('Fawaterak verifyCallback failed', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get price factor (default 1.0).
     *
     * @param mixed $paymentMethod
     * @return float
     */
    public function getPriceFactor(mixed $paymentMethod): float
    {
        // You could parse paymentMethod['commission'] to adjust price if you want
        return 1.0;
    }

    /**
     * Get the payment methods supported for your account
     * @return array|mixed
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function getPaymentMethods(): mixed
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ])->get($this->apiUrl . 'getPaymentmethods');

        if (!$response->successful()) {
            throw new RuntimeException('Failed to fetch payment methods: ' . $response->body());
        }

        return $response->json('data');
    }
}
