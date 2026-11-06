<?php

namespace App\Console\Commands;

use App\Models\AgentMemory;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class ForgetExpiredMemories extends Command
{
    protected $signature = 'agents:forget-expired';

    protected $description = 'Delete memories past the retention their agent was set up with';

    public function handle(): int
    {
        $count = TenantContext::system(function () {
            $expired = AgentMemory::where('expires_at', '<', now())->get();
            $expired->each->forceDelete();

            return $expired->count();
        });

        $this->info("Forgot {$count} memory(ies).");

        return self::SUCCESS;
    }
}
