<?php

namespace App\Console\Commands;

use App\Services\Governance\AuditChain;
use Illuminate\Console\Command;

class SealAuditLog extends Command
{
    protected $signature = 'agents:seal-audit';

    protected $description = 'Link new audit entries into the tamper-evident chain';

    public function handle(AuditChain $chain): int
    {
        $this->info('Sealed '.$chain->seal().' entries.');

        return self::SUCCESS;
    }
}
