<?php

namespace App\Console\Commands;

use App\Services\Runs\RunSweeper;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class SweepAgentRuns extends Command
{
    protected $signature = 'agents:sweep-runs';

    protected $description = 'Put runs a dead worker left behind back on the queue';

    public function handle(RunSweeper $sweeper): int
    {
        $swept = TenantContext::system(fn () => $sweeper->sweep());

        $this->info("Resumed {$swept['resumed']} run(s), {$swept['unknown']} effect(s) marked unknown.");

        return self::SUCCESS;
    }
}
