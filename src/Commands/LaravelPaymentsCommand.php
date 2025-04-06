<?php

namespace EngAlalfy\LaravelPayments\Commands;

use Illuminate\Console\Command;

class LaravelPaymentsCommand extends Command
{
    public $signature = 'laravel-payments';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
