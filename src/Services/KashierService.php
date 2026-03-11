<?php

namespace EngAlalfy\LaravelPayments\Services;

use EngAlalfy\LaravelPayments\Interfaces\PaymentGatewayInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KashierService implements PaymentGatewayInterface
{
    private const TEST_API_URL = 'https://test-api.kashier.io';
    private const LIVE_API_URL = 'https://api.kashier.io';

    private string $baseUrl;
    private string $merchantId;
    private string $secretKey;
    private string $apiKey;
    private string $mode;
    private string $redirectUrl;
    private string $currency;
    private string $display;
    private string $allowedMethods;

    public function __construct(?array $credential = null)
    {
        $merchantId = data_get($credential, 'merchant_id');
        $secretKey = data_get($credential, 'secret_key');
        $apiKey = data_get($credential, 'api_key');

        if ($merchantId && $secretKey && $apiKey) {
            $mode = data_get($credential, 'mode', 'live');
            $this->baseUrl = $mode === 'test' ? self::TEST_API_URL : self::LIVE_API_URL;
            $this->merchantId = $merchantId;
            $this->secretKey = $secretKey;
            $this->apiKey = $apiKey;
            $this->mode = $mode;
            $this->redirectUrl = data_get($credential, 'redirect_url', '');
            $this->currency = data_get($credential, 'currency', 'EGP');
            $this->display = data_get($credential, 'display', 'en');
            $this->allowedMethods = data_get($credential, 'allowed_methods', 'card,wallet,bank_installments');
        } else {
            $mode = config('payments.kashier.mode', 'live');
            $this->baseUrl = $mode === 'test' ? self::TEST_API_URL : self::LIVE_API_URL;
            $this->merchantId = config('payments.kashier.merchant_id', '');
            $this->secretKey = config('payments.kashier.secret_key', '');
            $this->apiKey = config('payments.kashier.api_key', '');
            $this->mode = $mode;
            $this->redirectUrl = config('payments.kashier.redirect_url', '');
            $this->currency = config('payments.kashier.currency', 'EGP');
            $this->display = config('payments.kashier.display', 'en');
            $this->allowedMethods = config('payments.kashier.allowed_methods', 'card,wallet,bank_installments');
        }
    }

    /**
     * Initialize a payment by creating a Kashier payment session (v3 API).
     *
     * @param string $orderId The unique identifier for the order.
     * @param float $amount The total amount for the transaction.
     * @param array $data Additional data for the payment session.
     *   Supported keys:
     *   - expire_at (string)              : When the session expires (ISO 8601). Defaults to +24 hours.
     *   - max_failure_attempts (int)       : Max payment attempts. Default: 3.
     *   - payment_type (string)            : e.g., "credit". Default: "credit".
     *   - currency (string)                : Overrides default currency.
     *   - merchant_redirect (string)       : Overrides default redirect URL.
     *   - display (string)                 : "en" or "ar". Overrides default.
     *   - type (string)                    : e.g., "one-time". Default: "one-time".
     *   - allowed_methods (string)         : e.g., "card,wallet". Overrides default.
     *   - redirect_method (string|null)    : "get" or "post". Default: null.
     *   - failure_redirect (bool)       : Redirect on failure. Default: true.
     *   - brand_color (string)             : Hex color for branding.
     *   - default_method (string)          : Default payment method tab.
     *   - description (string)             : Order description (max 120 chars).
     *   - manual_capture (bool)            : Authorize first then capture. Default: false.
     *   - customer (array)                 : ['email' => '...', 'reference' => '...'].
     *   - save_card (string)               : "optional" or "forced".
     *   - retrieve_saved_card (bool)       : Retrieve saved cards. Default: false.
     *   - interaction_source (string)      : "ECOMMERCE" or "MOTO".
     *   - enable_3ds (bool)                : Enable 3DS. Default: true.
     *   - server_webhook (string)          : Webhook URL for server-to-server notifications.
     *   - notes (string)                   : Additional notes.
     *   - meta_data (array)             : Metadata including displayNotes.
     *   - iframe_background_color (string) : Hex color for iframe background.
     *   - connected_account (string)    : Sub-merchant ID for connected accounts.
     * @return array|string The full API response.
     *
     * @throws \Exception If session creation fails.
     */
    public function initializePayment(string $orderId, float $amount, array $data): array|string
    {
        try {
            if (empty($orderId) || $amount <= 0) {
                throw new RuntimeException('Invalid order ID or amount for payment initialization');
            }

            $result = $this->createPaymentSession($orderId, $amount, $data);

            if (!$result['success']) {
                throw new RuntimeException('Failed to create Kashier payment session: ' . ($result['message'] ?? 'Unknown error'));
            }

            return [
                'success' => true,
                'data' => $result['data'],
                'raw' => $result['raw'],
            ];
        } catch (Exception $e) {
            Log::error('Kashier initializePayment failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a payment session via Kashier v3 API.
     *
     * @param string $orderId
     * @param float $amount
     * @param array $data
     * @return array
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    private function createPaymentSession(string $orderId, float $amount, array $data): array
    {
        $payload = [
            'expireAt' => $data['expire_at'] ?? now()->addDay()->toISOString(),
            'maxFailureAttempts' => $data['max_failure_attempts'] ?? 3,
            'paymentType' => $data['payment_type'] ?? 'credit',
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $data['currency'] ?? $this->currency,
            'order' => $orderId,
            'merchantRedirect' => $data['merchant_redirect'] ?? $this->redirectUrl,
            'display' => $data['display'] ?? $this->display,
            'type' => $data['type'] ?? 'one-time',
            'allowedMethods' => $data['allowed_methods'] ?? $this->allowedMethods,
            'merchantId' => $this->merchantId,

        ];

        // Optional parameters (matching the Kashier v3 API body)
        $optionalMappings = [
            'redirect_method' => 'redirectMethod',
            'iframe_background_color' => 'iframeBackgroundColor',
            'meta_data' => 'metaData',
            'failure_redirect' => 'failureRedirect',
            'brand_color' => 'brandColor',
            'default_method' => 'defaultMethod',
            'description' => 'description',
            'manual_capture' => 'manualCapture',
            'customer' => 'customer',
            'save_card' => 'saveCard',
            'retrieve_saved_card' => 'retrieveSavedCard',
            'interaction_source' => 'interactionSource',
            'enable_3ds' => 'enable3DS',
            'server_webhook' => 'serverWebhook',
            'notes' => 'notes',
        ];

        foreach ($optionalMappings as $dataKey => $apiKey) {
            if (array_key_exists($dataKey, $data)) {
                $payload[$apiKey] = $data[$dataKey];
            }
        }

        if($this->mode === 'test') {
            info('Kashier Payload', $payload);
            info('Kashier URL', [
                "url" => $this->baseUrl . '/v3/payment/sessions'
            ]);
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => $this->secretKey,
            'api-key' => $this->apiKey,
        ])->post($this->baseUrl . '/v3/payment/sessions', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Kashier API error: ' . $response->body());
        }

        $result = $response->json();

        return [
            'success' => true,
            'data' => $result,
            'raw' => $result,
        ];
    }

    /**
     * Extract the session URL from the payment session response.
     *
     * @param mixed $data The response from initializePayment.
     * @return string The session URL for redirecting the customer.
     *
     * @throws RuntimeException If session URL is missing.
     */
    public function getCheckoutUrl(mixed $data): string
    {
        $sessionUrl = data_get($data, 'data.sessionUrl');

        if (empty($sessionUrl)) {
            throw new RuntimeException('Kashier session URL not found in response');
        }

        return $sessionUrl;
    }

    /**
     * Verify the callback signature from Kashier to ensure it is valid.
     *
     * @param mixed $data The callback query parameters.
     * @return bool True if the signature is valid, false otherwise.
     */
    public function verifyCallback(mixed $data): bool
    {
        if (empty($data) || !is_array($data) || array_key_exists('signature', $data) === false) {
            throw new RuntimeException('Invalid callback data for signature verification');
        }

        $queryString = '';
        foreach ($data as $key => $value) {
            if ($key === 'signature' || $key === 'mode') {
                continue;
            }
            $queryString .= '&' . $key . '=' . $value;
        }

        $queryString = ltrim($queryString, '&');

        $signature = hash_hmac('sha256', $queryString, $this->secretKey, false);

        return $signature === data_get($data, 'signature');
    }

    /**
     * Get the price factor based on the payment method.
     *
     * @param mixed $paymentMethod The payment method to evaluate.
     * @return float The price factor.
     */
    public function getPriceFactor(mixed $paymentMethod): float
    {
        return 1.0;
    }
}
