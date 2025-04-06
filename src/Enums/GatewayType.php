<?php

namespace EngAlalfy\LaravelPayments\Enums;

enum GatewayType: string
{
    case PAYMOB = 'paymob';
    case KASHIER = 'kashier';
    case FAWRY = 'fawry';
    case OPAY = 'opay';
}
