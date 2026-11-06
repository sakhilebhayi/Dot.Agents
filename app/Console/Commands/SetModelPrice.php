<?php

namespace App\Console\Commands;

use App\Models\ModelPrice;
use Illuminate\Console\Command;

/** Prices are never invented. A person reads them from the provider and enters them here, with a date and a source. */
class SetModelPrice extends Command
{
    protected $signature = 'agents:set-price {provider} {model} {input : per million input tokens} {output : per million output tokens} {--cached= : per million cached tokens} {--from= : the date it applies from} {--source= : where the figure came from}';

    protected $description = 'Record what a model costs, from a date';

    public function handle(): int
    {
        if (blank($this->option('source'))) {
            $this->error('Say where this price came from with --source.');

            return self::FAILURE;
        }

        ModelPrice::create([
            'provider' => $this->argument('provider'),
            'model' => $this->argument('model'),
            'input_per_million' => $this->argument('input'),
            'output_per_million' => $this->argument('output'),
            'cache_read_per_million' => $this->option('cached'),
            'effective_from' => $this->option('from') ?? now()->toDateString(),
            'source' => $this->option('source'),
        ]);

        $this->info('Recorded. Runs from that date are costed at this price. Earlier runs keep theirs.');

        return self::SUCCESS;
    }
}
