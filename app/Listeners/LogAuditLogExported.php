<?php

namespace App\Listeners;

use App\Events\AuditLogExported;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogAuditLogExported implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function handle(AuditLogExported $event): void
    {
        Log::info('[Governance] Audit log exported', [
            'organization_id' => $event->organizationId,
            'format' => $event->format,
            'from_date' => $event->fromDate,
            'to_date' => $event->toDate,
        ]);
    }
}
