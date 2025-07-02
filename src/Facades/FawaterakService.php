<?php

namespace EngAlalfy\LaravelPayments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \EngAlalfy\LaravelPayments\Services\FawaterakService
 */
class FawaterakService extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \EngAlalfy\LaravelPayments\Services\FawaterakService::class;
    }
}
