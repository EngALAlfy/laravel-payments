<?php

namespace EngAlalfy\LaravelPayments\Services;

use Illuminate\Http\Request;

class KashierService
{
    private string $baseUrl;
    private string $merchantId;
    private string $secret;
    private string $mode;
    private string $redirectUrl;
    private string $currency;
    private string $display;
    private string $redirectMethod;

    /**
     */
    public function __construct()
    {
        $this->baseUrl = "https://checkout.kashier.io";
        $this->merchantId = settings("kashier_merchant_id" , "");
        $this->secret = settings("kashier_api_key" , "");
        $this->mode = "live";
        $this->redirectUrl = route("services.orders.orderKashierPayment");
        $this->currency = "EGP";
        $this->display = "ar";
        $this->redirectMethod = "get";
    }


    /**
     * @param string $orderId
     * @param float $amount
     * @param string $metaData
     * @param string $paymentRequestId
     * @param string|null $serverWebhook
     * @return string
     */
    public function getPayNowUrl(
        string $orderId,
        float $amount,
        string $metaData,
        string $paymentRequestId,
    ): string {
        if(strtoupper(settings("currency_name" , "EGP")) !== "EGP"){
            $amount = settings("currency_rate" , 1) * $amount;
        }

        $hash = $this->generateKashierOrderHash($orderId,$amount);

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


    private function generateKashierOrderHash(string $orderId, $amount): string
    {
        $path = "/?payment=".$this->merchantId.".".$orderId.".".$amount.".".$this->currency;
        return hash_hmac( 'sha256' , $path , $this->secret ,false);
    }

    public function verifySignature(Request $request): bool
    {
        $queryString = "";

        foreach ($request->query() as $key => $value) {
            if ($key === "signature" || $key === "mode") {
                continue;
            }
            $queryString .= "&" . $key . "=" . $value;
        }

        $queryString = ltrim($queryString, '&');

        $signature = hash_hmac('sha256', $queryString, $this->secret, false);

        return $signature === $request->query('signature');
    }

}
