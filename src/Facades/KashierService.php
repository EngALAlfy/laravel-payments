<?php

namespace EngAlalfy\LaravelPayments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \EngAlalfy\LaravelPayments\Services\KashierService
 */
class KashierService extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \EngAlalfy\LaravelPayments\Services\KashierService::class;
    }
}
