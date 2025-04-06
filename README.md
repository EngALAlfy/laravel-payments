
# Laravel Payments

A unified Laravel package for multiple payment gateways (Paymob, Kashier, etc.) with easy integration and extensibility. Simplify your payment processing with a consistent API across different payment providers.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/engalalfy/laravel-payments.svg?style=flat-square)](https://packagist.org/packages/engalalfy/laravel-payments)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/engalalfy/laravel-payments/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/engalalfy/laravel-payments/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/engalalfy/laravel-payments/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/engalalfy/laravel-payments/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/engalalfy/laravel-payments.svg?style=flat-square)](https://packagist.org/packages/engalalfy/laravel-payments)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Issues](https://img.shields.io/github/issues/engalalfy/laravel-payments.svg?style=flat-square)](https://github.com/engalalfy/laravel-payments/issues)
[![Pull Requests](https://img.shields.io/github/issues-pr/engalalfy/laravel-payments.svg?style=flat-square)](https://github.com/engalalfy/laravel-payments/pulls)

## Features

- **Unified API**: Work with multiple payment gateways using a consistent interface
- **Extensible**: Easily add support for new payment gateways
- **Provider Agnostic**: Switch between payment providers without changing your application code
- **Event Driven**: Leverage Laravel's event system for payment notifications
- **Thorough Documentation**: Well-documented API with examples for each supported gateway
- **Robust Testing**: Comprehensive test suite for reliability

## Package Structure

```
laravel-payments/
├── config/                  # Configuration files
├── database/
│   └── migrations/          # Database migrations
├── resources/
│   └── views/               # Views for payment pages if needed
├── src/
│   ├── Facades/             # Laravel Facades
│   ├── Gateways/            # Payment gateway implementations
│   │   ├── Paymob/
│   │   ├── Kashier/
│   │   └── ...
│   ├── Contracts/           # Interfaces defining the API
│   ├── Models/              # Eloquent models
│   ├── Events/              # Payment-related events
│   ├── Exceptions/          # Custom exceptions
│   └── LaravelPayments.php  # Main package class
└── tests/                   # Test suite
```

## Installation

You can install the package via composer:

```bash
composer require engalalfy/laravel-payments
```

After installation, publish the configuration and migrations:

```bash
php artisan vendor:publish --tag="laravel-payments-config"
```

## Configuration

Configure your payment gateways in the published config file:

```php
// config/laravel-payments.php

return [
    'default' => env('PAYMENT_GATEWAY', 'paymob'),
    
    'gateways' => [
        'paymob' => [
            'api_key' => env('PAYMOB_API_KEY'),
            'integration_id' => env('PAYMOB_INTEGRATION_ID'),
            'iframe_id' => env('PAYMOB_IFRAME_ID'),
            // Additional Paymob configuration...
        ],
        
        'kashier' => [
            'merchant_id' => env('KASHIER_MERCHANT_ID'),
            'api_key' => env('KASHIER_API_KEY'),
            // Additional Kashier configuration...
        ],
        
        // Additional payment gateways...
    ],
    
    'currency' => env('PAYMENT_CURRENCY', 'EGP'),
    'callback_url' => env('PAYMENT_CALLBACK_URL', '/payment/callback'),
];
```

## Basic Usage


## Testing

The package comes with a comprehensive test suite:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details on how to contribute to this package.

### Reporting Issues

If you encounter any issues, please [open an issue](https://github.com/engalalfy/laravel-payments/issues/new) on GitHub.

### Pull Requests

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Support the Development

If you find this package helpful, consider supporting its development:

[![PayPal](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://paypal.me/islamalalfy)

## Credits

- [Islam H ALAlfy](https://github.com/EngAlalfy)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
