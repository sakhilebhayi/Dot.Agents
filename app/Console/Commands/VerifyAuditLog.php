<?php

namespace App\Console\Commands;

use App\Services\Governance\AuditChain;
use Illuminate\Console\Command;

class VerifyAuditLog extends Command
{
    protected $signature = 'agents:verify-audit';

    protected $description = 'Check that no sealed audit entry has been changed or removed';

    public function handle(AuditChain $chain): int
    {
        $broken = $chain->verify();

        if ($broken === []) {
            $this->info('The audit chain is whole.');

            return self::SUCCESS;
        }

        foreach ($broken as $problem) {
            $this->error($problem);
        }

        return self::FAILURE;
    }
}
