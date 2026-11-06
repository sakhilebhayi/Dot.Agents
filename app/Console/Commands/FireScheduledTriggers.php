<?php

namespace App\Console\Commands;

use App\Models\AgentTrigger;
use App\Services\Triggers\ScheduleClock;
use App\Services\Triggers\TriggerFirer;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class FireScheduledTriggers extends Command
{
    protected $signature = 'agents:fire-schedules';

    protected $description = 'Start work for every schedule that has come due';

    public function handle(TriggerFirer $firer): int
    {
        $fired = 0;

        TenantContext::system(fn () => AgentTrigger::where('kind', AgentTrigger::SCHEDULE)->where('is_active', true)
            ->where('next_run_at', '<=', now())->orderBy('next_run_at')
            ->each(function (AgentTrigger $trigger) use ($firer, &$fired) {
                TenantContext::forOrganization($trigger->organization_id, function () use ($trigger, $firer, &$fired) {
                    // Moved on first, so a crash can miss one fire but can never repeat it forever.
                    $due = $trigger->next_run_at;
                    $limit = (int) ($trigger->settings['max_runs'] ?? 0);
                    $spent = $limit > 0 && $trigger->run_count + 1 >= $limit;

                    $trigger->update(['next_run_at' => $spent ? null : ScheduleClock::next($trigger->settings), 'is_active' => ! $spent && ScheduleClock::next($trigger->settings) !== null]);

                    if ($firer->fire($trigger, $due->format('YmdHi')) !== null) {
                        $fired++;
                    }
                });
            }));

        $this->info("Started {$fired} scheduled run(s).");

        return self::SUCCESS;
    }
}
