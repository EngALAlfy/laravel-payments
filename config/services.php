return [

        'paymob' => [
            'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com/v1'),
            'checkout_url' => env('PAYMOB_CHECKOUT_URL', 'https://accept.paymob.com/unifiedcheckout'),
            'public_key' => env('PAYMOB_PUBLIC_KEY'),
            'secret_key' => env('PAYMOB_SECRET_KEY'),
            'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
        ],

    ];
