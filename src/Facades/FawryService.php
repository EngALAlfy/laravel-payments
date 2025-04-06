<?php

namespace EngAlalfy\LaravelPayments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \EngAlalfy\LaravelPayments\Services\FawryService
 */
class FawryService extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \EngAlalfy\LaravelPayments\Services\FawryService::class;
    }
}
