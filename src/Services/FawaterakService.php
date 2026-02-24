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
    private string $apiUrl;
    private string $token;

    public function __construct(?array $credential = null)
    {
        $apiUrl = data_get($credential, "api_url");
        $token = data_get($credential, "token");
        if ($apiUrl && $token) {
            $this->apiUrl = $apiUrl;
            $this->token = $token;
        } else {
            $this->apiUrl = config('payments.fawaterak.api_url', 'https://staging.fawaterk.com/api/v2/');
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
     */
    public function initializePayment(string $orderId, float $amount, array $data): array|string
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ])->get($this->apiUrl . 'getPaymentmethods');

            if (!$response->successful()) {
                throw new RuntimeException('Failed to fetch payment methods: ' . $response->body());
            }

            $result = $response->json();

            return [
                'success' => true,
                'data' => $result['data'] ?? [],
                'raw' => $result
            ];

        } catch (Exception $e) {
            Log::error('Fawaterak initializePayment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute payment & return checkout URL or payment code.
     *
     * @param mixed $data Array with required fields.
     * @return string Checkout URL or JSON-encoded payment data (for codes)
     */
    public function getCheckoutUrl(mixed $data): string
    {
        try {
            // Validate required fields
            $required = [
                'payment_method_id', 'cartTotal', 'currency', 'invoice_number',
                'customerData', 'cartItems', 'redirectionUrls'
            ];

            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new RuntimeException("Missing required field: $field");
                }
            }

            // Call createInvoice() from same service
            $response = $this->createInvoice(
                paymentMethodId: $data['payment_method_id'],
                cartTotal: $data['cartTotal'],
                currency: $data['currency'],
                invoiceNumber: $data['invoice_number'],
                customerData: $data['customerData'],
                cartItems: $data['cartItems'],
                redirectionUrls: $data['redirectionUrls'],
                options: $data['options'] ?? []
            );

            if (!$response['success']) {
                throw new RuntimeException('Failed to create invoice: ' . ($response['message'] ?? 'Unknown error'));
            }

            $paymentData = $response['data']['payment_data'] ?? [];

            /**
             * Logic:
             * - if redirectTo present → return URL (Visa/Mastercard)
             * - else return JSON-encoded codes (e.g., fawryCode, amanCode etc.)
             */

            if (isset($paymentData['redirectTo'])) {
                return $paymentData['redirectTo'];
            }

            // fallback: return payment code as JSON
            return json_encode($paymentData);

        } catch (Exception $e) {
            Log::error('Fawaterak getCheckoutUrl failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);

            throw new RuntimeException('Error getting checkout URL: ' . $e->getMessage());
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
    ): array
    {
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
                'frequency', 'customExpireDate', 'discountData', 'taxData',
                'authAndCapture', 'payLoad', 'mobileWalletNumber',
                'due_date', 'sendEmail', 'sendSMS', 'lang', 'redirectOption',
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

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify callback (usually needs endpoint to handle Fawaterak notifications).
     *
     * @param Request $request
     * @return array|bool
     */
    public function verifyCallback(Request $request): array|bool
    {
        // Since Fawaterak docs didn’t describe the callback verification yet,
        // return basic log & data. You can implement when actual callback data arrives.

        try {
            Log::info('Fawaterak callback received', $request->all());

            return [
                'success' => true,
                'data' => $request->all(),
                'message' => 'Callback received (implement real verification later)',
            ];

        } catch (Exception $e) {
            Log::error('Fawaterak verifyCallback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
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

}
