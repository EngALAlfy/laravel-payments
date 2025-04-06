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
];
