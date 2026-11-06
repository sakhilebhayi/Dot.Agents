<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\Governance\DigitalImmuneSystem;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunDigitalImmuneSystemCheck implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly ?int $organizationId = null
    ) {
        $this->onQueue('governance');
    }

    public function handle(DigitalImmuneSystem $dis): void
    {
        if ($this->organizationId !== null) {
            $this->runForOrganization($dis, $this->organizationId);

            return;
        }

        // Run for all active organizations
        Organization::where('status', 'active')
            ->select('id')
            ->each(function (Organization $org) use ($dis) {
                try {
                    $this->runForOrganization($dis, $org->id);
                } catch (Throwable $e) {
                    Log::error('[DIS] Check failed for organization', [
                        'organization_id' => $org->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }

    private function runForOrganization(DigitalImmuneSystem $dis, int $orgId): void
    {
        $report = TenantContext::forOrganization($orgId, fn () => $dis->runHealthCheck($orgId));

        Log::info('[DIS] Health check complete', [
            'organization_id' => $orgId,
            'total_agents' => $report['total_agents'],
            'healthy' => $report['healthy'],
            'warnings' => $report['warnings'],
            'critical' => $report['critical'],
            'quarantined' => $report['quarantined'],
            'event_count' => count($report['events']),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('[DIS] Digital Immune System check failed', [
            'organization_id' => $this->organizationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
