<?php

namespace EngAlalfy\LaravelPayments\Services;

use EngAlalfy\LaravelPayments\Interfaces\PaymentGatewayInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaytabsService implements PaymentGatewayInterface
{
    private string $serverKey;
    private int $profileId;
    private string $baseUrl;
    private string $returnUrl;
    private string $callbackUrl;
    private string $currency;

    public function __construct(?array $credential = null)
    {
        $serverKey = data_get($credential, 'server_key');
        $profileId = data_get($credential, 'profile_id');
        $baseUrl = data_get($credential, 'base_url');
        $returnUrl = data_get($credential, 'return_url');
        $callbackUrl = data_get($credential, 'callback_url');
        $currency = data_get($credential, 'currency');

        if ($serverKey && $profileId) {
            $this->serverKey = $serverKey;
            $this->profileId = (int) $profileId;
            $this->baseUrl = $baseUrl ?? 'https://secure-global.paytabs.com';
            $this->returnUrl = $returnUrl ?? '';
            $this->callbackUrl = $callbackUrl ?? '';
            $this->currency = $currency ?? 'USD';
        } else {
            $this->serverKey = config('payments.paytabs.server_key', '');
            $this->profileId = (int) config('payments.paytabs.profile_id', 0);
            $this->baseUrl = config('payments.paytabs.base_url', 'https://secure-global.paytabs.com');
            $this->returnUrl = config('payments.paytabs.return_url', '');
            $this->callbackUrl = config('payments.paytabs.callback_url', '');
            $this->currency = config('payments.paytabs.currency', 'USD');
        }
    }

    /**
     * Initialize a payment process by creating a Hosted Payment Page request.
     *
     * @param string $orderId The unique identifier for the order (cart_id).
     * @param float $amount The total amount for the transaction (cart_amount).
     * @param array $data Additional data required for payment initialization.
     *                     Supports: description, customer_data (name, email, phone, street1, city, state, country, zip),
     *                     tran_type (default: sale), tran_class (default: ecom), hide_shipping, paypage_lang.
     * @return array|string The response from the payment creation.
     */
    public function initializePayment(string $orderId, float $amount, array $data): array|string
    {
        $description = $data['description'] ?? 'Payment for order ' . $orderId;
        $customerData = $data['customer_data'] ?? [];

        if (empty($orderId) || $amount <= 0) {
            throw new RuntimeException('Invalid order ID or amount for payment initialization');
        }

        return $this->createPaymentRequest($orderId, $amount, $description, $customerData, $data);
    }

    /**
     * Get the checkout URL for client-side redirection.
     *
     * @param mixed $data Data required to generate the URL.
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
     * Verify the callback/return from PayTabs to ensure it is valid.
     *
     * Validates the signature using HMAC SHA256 as per PayTabs documentation.
     *
     * @param mixed $data The POST data received from PayTabs return/callback.
     * @return array|bool The result of the verification process.
     */
    public function verifyCallback(mixed $data): array|bool
    {
        try {
            if (is_array($data)) {
                $postValues = $data;
            } elseif ($data instanceof Request) {
                $postValues = $data->all();
            } else {
                throw new RuntimeException('Invalid callback data format');
            }

            $isValid = $this->isValidRedirect($postValues);

            if (!$isValid) {
                throw new RuntimeException('Invalid payment signature — verification failed');
            }

            $respStatus = $postValues['respStatus'] ?? null;
            $isPaid = $respStatus === 'A'; // 'A' = Authorised

            return [
                'success' => true,
                'is_paid' => $isPaid,
                'data' => [
                    'tran_ref' => $postValues['tranRef'] ?? null,
                    'cart_id' => $postValues['cartId'] ?? null,
                    'customer_email' => $postValues['customerEmail'] ?? null,
                    'resp_code' => $postValues['respCode'] ?? null,
                    'resp_message' => $postValues['respMessage'] ?? null,
                    'resp_status' => $respStatus,
                    'acquirer_message' => $postValues['acquirerMessage'] ?? null,
                    'acquirer_rrn' => $postValues['acquirerRRN'] ?? null,
                    'token' => $postValues['token'] ?? null,
                ],
                'message' => 'Payment verification successful',
            ];

        } catch (Exception $e) {
            Log::error('PayTabs verification failed', [
                'error' => $e->getMessage(),
                'data' => $data,
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
     * Create a payment request via PayTabs Hosted Payment Page API.
     *
     * @param string $orderId The unique identifier for the order.
     * @param float $amount The total amount for the transaction.
     * @param string $description Description of the payment.
     * @param array $customerData Customer information.
     * @param array $extraData Additional optional parameters.
     * @return array Response containing payment details and redirect URL.
     *
     * @throws RuntimeException If payment creation fails.
     */
    private function createPaymentRequest(
        string $orderId,
        float $amount,
        string $description,
        array $customerData = [],
        array $extraData = []
    ): array {
        try {
            $paymentData = [
                'profile_id' => $this->profileId,
                'tran_type' => $extraData['tran_type'] ?? 'sale',
                'tran_class' => $extraData['tran_class'] ?? 'ecom',
                'cart_id' => $orderId,
                'cart_description' => $description,
                'cart_currency' => $extraData['currency'] ?? $this->currency,
                'cart_amount' => $amount,
                'hide_shipping' => $extraData['hide_shipping'] ?? true,
            ];

            // Set return & callback URLs
            if (!empty($this->returnUrl)) {
                $paymentData['return'] = $this->returnUrl;
            }

            if (!empty($this->callbackUrl)) {
                $paymentData['callback'] = $this->callbackUrl;
            }

            // Add customer details if provided
            if (!empty($customerData)) {
                $customerDetails = [];

                if (!empty($customerData['name'])) {
                    $customerDetails['name'] = $customerData['name'];
                }
                if (!empty($customerData['email'])) {
                    $customerDetails['email'] = $customerData['email'];
                }
                if (!empty($customerData['phone'])) {
                    $customerDetails['phone'] = $customerData['phone'];
                }
                if (!empty($customerData['street1'])) {
                    $customerDetails['street1'] = $customerData['street1'];
                }
                if (!empty($customerData['city'])) {
                    $customerDetails['city'] = $customerData['city'];
                }
                if (!empty($customerData['state'])) {
                    $customerDetails['state'] = $customerData['state'];
                }
                if (!empty($customerData['country'])) {
                    $customerDetails['country'] = $customerData['country'];
                }
                if (!empty($customerData['zip'])) {
                    $customerDetails['zip'] = $customerData['zip'];
                }

                if (!empty($customerDetails)) {
                    $paymentData['customer_details'] = $customerDetails;
                }
            }

            // Optional: paypage language
            if (!empty($extraData['paypage_lang'])) {
                $paymentData['paypage_lang'] = $extraData['paypage_lang'];
            }

            // Optional: user defined fields
            if (!empty($extraData['user_defined'])) {
                $paymentData['user_defined'] = $extraData['user_defined'];
            }

            // Make API request to PayTabs
            $response = Http::withHeaders([
                'authorization' => $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/payment/request', $paymentData);

            if (!$response->successful()) {
                throw new RuntimeException('Failed to create payment request: ' . $response->body());
            }

            $result = $response->json();

            // Check if the redirect URL was generated successfully
            if (empty($result['redirect_url'])) {
                $errorMessage = $result['message'] ?? 'Invalid response from payment gateway';
                throw new RuntimeException($errorMessage);
            }

            return [
                'success' => true,
                'redirect_url' => $result['redirect_url'],
                'order_id' => $orderId,
                'tran_ref' => $result['tran_ref'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error('PayTabs payment creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Error creating payment: ' . $e->getMessage());
        }
    }

    /**
     * Validate the return/redirect response signature from PayTabs.
     *
     * As per PayTabs documentation (Step 5):
     * 1. Remove empty parameters and the signature field
     * 2. Sort by keys
     * 3. URL-encode and build query string
     * 4. HMAC SHA256 with server key
     * 5. Compare with received signature
     *
     * @param array $postValues The POST values from the return redirect.
     * @return bool Whether the signature is valid.
     */
    private function isValidRedirect(array $postValues): bool
    {
        if (empty($postValues) || !array_key_exists('signature', $postValues)) {
            return false;
        }

        $requestSignature = $postValues['signature'];
        unset($postValues['signature']);

        // Remove empty values
        $fields = array_filter($postValues);

        // Sort by keys
        ksort($fields);

        // Build URL-encoded query string
        $query = http_build_query($fields);

        return $this->isGenuine($query, $requestSignature);
    }

    /**
     * Verify the HMAC SHA256 signature.
     *
     * @param string $data The query string data.
     * @param string $requestSignature The signature received from PayTabs.
     * @return bool Whether the signature matches.
     */
    private function isGenuine(string $data, string $requestSignature): bool
    {
        $signature = hash_hmac('sha256', $data, $this->serverKey);

        return hash_equals($signature, $requestSignature);
    }
}
