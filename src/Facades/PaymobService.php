<?php

namespace EngAlalfy\LaravelPayments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \EngAlalfy\LaravelPayments\Services\PaymobService
 */
class PaymobService extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \EngAlalfy\LaravelPayments\Services\PaymobService::class;
    }
}
