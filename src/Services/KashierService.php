<?php

namespace EngAlalfy\LaravelPayments\Services;

use EngAlalfy\LaravelPayments\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use RuntimeException;

class KashierService implements PaymentGatewayInterface
{
    private string $baseUrl;
    private string $merchantId;
    private string $secret;
    private string $mode;
    private string $redirectUrl;
    private string $currency;
    private string $display;
    private string $redirectMethod;

    public function __construct()
    {
        $this->baseUrl = config('payments.kashier.base_url', 'https://checkout.kashier.io');
        $this->merchantId = config('payments.kashier.merchant_id', '');
        $this->secret = config('payments.kashier.api_key', '');
        $this->mode = config('payments.kashier.mode', 'live');
        $this->redirectUrl = config('payments.kashier.redirect_url', null);
        $this->currency = config('payments.kashier.currency', 'EGP');
        $this->display = config('payments.kashier.display', 'ar');
        $this->redirectMethod = config('payments.kashier.redirect_method', 'get');
    }

    /**
     * Initialize a payment process by generating a payment URL.
     *
     * @param string $orderId The unique identifier for the order.
     * @param float $amount The total amount for the transaction.
     * @param array $data Additional data required for payment initialization.
     * @return string The payment URL to redirect the user to.
     * @throws RuntimeException If payment URL generation fails.
     */
    public function initializePayment(string $orderId, float $amount, array $data): string
    {
        $metaData = $data['meta_data'] ?? '';
        $paymentRequestId = $data['payment_request_id'] ?? '';

        if (empty($orderId) || $amount <= 0) {
            throw new RuntimeException('Invalid order ID or amount for payment initialization');
        }

        return $this->getPayNowUrl(
            $orderId,
            $amount,
            $metaData,
            $paymentRequestId
        );
    }

    /**
     * Get the checkout URL for client-side redirection.
     *
     * @param mixed $data Data required to generate the URL.
     * @return string The checkout URL.
     */
    public function getCheckoutUrl(mixed $data): string
    {
        return $data;
    }

    /**
     * Verify the callback signature from Kashier to ensure it is valid.
     *
     * @param Request $request The HTTP request containing the callback data.
     * @return bool True if the signature is valid, false otherwise.
     */
    public function verifyCallback(Request $request): bool
    {
        $queryString = '';

        foreach ($request->query() as $key => $value) {
            if ($key === 'signature' || $key === 'mode') {
                continue;
            }
            $queryString .= '&'.$key.'='.$value;
        }

        $queryString = ltrim($queryString, '&');

        $signature = hash_hmac('sha256', $queryString, $this->secret, false);

        return $signature === $request->query('signature');
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

    /**
     * Generate a payment URL for the Kashier service.
     *
     * @param string $orderId The unique identifier for the order.
     * @param float $amount The total amount for the transaction.
     * @param string $metaData Additional metadata for the payment.
     * @param string $paymentRequestId The payment request identifier.
     * @return string The generated payment URL.
     */
    private function getPayNowUrl(
        string $orderId,
        float $amount,
        string $metaData,
        string $paymentRequestId,
    ): string {
        $hash = $this->generateKashierOrderHash($orderId, $amount);

        return sprintf(
            '%s/?merchantId=%s&orderId=%s&amount=%s&currency=%s&hash=%s&mode=%s&merchantRedirect=%s&metaData=%s&paymentRequestId=%s&redirectMethod=%s&display=%s',
            $this->baseUrl,
            urlencode($this->merchantId),
            urlencode($orderId),
            urlencode($amount),
            urlencode($this->currency),
            urlencode($hash),
            urlencode($this->mode),
            urlencode($this->redirectUrl),
            urlencode($metaData),
            urlencode($paymentRequestId),
            urlencode($this->redirectMethod),
            urlencode($this->display)
        );
    }

    /**
     * Generate a hash for the Kashier order to ensure data integrity.
     *
     * @param string $orderId The unique identifier for the order.
     * @param float $amount The total amount for the transaction.
     * @return string The generated hash.
     */
    private function generateKashierOrderHash(string $orderId, $amount): string
    {
        $path = '/?payment='.$this->merchantId.'.'.$orderId.'.'.$amount.'.'.$this->currency;

        return hash_hmac('sha256', $path, $this->secret, false);
    }
}
