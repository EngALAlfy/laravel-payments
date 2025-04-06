<?php

namespace EngAlalfy\LaravelPayments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \EngAlalfy\LaravelPayments\Services\OPayService
 */
class OPayService extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \EngAlalfy\LaravelPayments\Services\OPayService::class;
    }
}
