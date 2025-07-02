<?php

// config for EngAlalfy/LaravelPayments
return [
    'paymob' => [
        'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com/v1'),
        'checkout_url' => env('PAYMOB_CHECKOUT_URL', 'https://accept.paymob.com/unifiedcheckout'),
        'public_key' => env('PAYMOB_PUBLIC_KEY'),
        'secret_key' => env('PAYMOB_SECRET_KEY'),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
    ],

    'kashier' => [
        'base_url' => env('KASHIER_BASE_URL', 'https://checkout.kashier.io'),
        'public_key' => env('KASHIER_PUBLIC_KEY'),
        'secret_key' => env('KASHIER_SECRET_KEY'),
        'merchant_id' => env('KASHIER_MERCHANT_ID', ''),
        'api_key' => env('KASHIER_API_KEY', ''),
        'mode' => env('KASHIER_MODE', 'live'),
        'redirect_url' => env('KASHIER_REDIRECT_URL', ''),
        'currency' => env('KASHIER_CURRENCY', 'EGP'),
        'display' => env('KASHIER_DISPLAY', 'ar'),
        'redirect_method' => env('KASHIER_REDIRECT_METHOD', 'get'),
    ],

    'telr' => [
        'merchant_id' => env('TELR_MERCHANT_ID', ''),
        'api_key' => env('TELR_API_KEY', ''),
        'test_mode' => env('TELR_TEST_MODE', false),
        'api_url' => env('TELR_API_URL', 'https://secure.telr.com/gateway/order.json'),
        'success_url' => env('TELR_SUCCESS_URL', ''),
        'cancel_url' => env('TELR_CANCEL_URL', ''),
        'decline_url' => env('TELR_DECLINE_URL', ''),
    ],

    'fawaterak' => [
        'api_url' => env('FAWATERAK_API_URL', 'https://staging.fawaterk.com/api/v2/'),
        'token' => env('FAWATERAK_TOKEN'),
    ],
];
