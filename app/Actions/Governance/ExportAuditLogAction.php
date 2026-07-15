<?php

declare(strict_types=1);

namespace App\Actions\Governance;

use App\Actions\Concerns\LogsActionErrors;
use App\DTOs\Governance\AuditLogExportParams;
use App\Events\AuditLogExported;
use App\Models\AuditLog;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportAuditLogAction
{
    use LogsActionErrors;

    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Export audit logs as a CSV or JSON download.
     * Only users with the audit log view permission may export.
     */
    public function execute(AuditLogExportParams $params): StreamedResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = AuditLog::withoutGlobalScope('organization')
            ->where('organization_id', $params->organizationId)
            ->when($params->fromDate, fn ($q) => $q->where('created_at', '>=', $params->fromDate))
            ->when($params->toDate, fn ($q) => $q->where('created_at', '<=', $params->toDate))
            ->when($params->eventCategory, fn ($q) => $q->where('event_category', $params->eventCategory))
            ->when($params->riskLevel, fn ($q) => $q->where('risk_level', $params->riskLevel))
            ->orderBy('created_at', 'desc');

        $this->auditService->logUserAction(
            event: 'audit_log.exported',
            description: "Audit log exported as {$params->format}",
            data: [
                'format' => $params->format,
                'from_date' => $params->fromDate,
                'to_date' => $params->toDate,
                'event_category' => $params->eventCategory,
                'risk_level' => $params->riskLevel,
            ],
        );

        event(new AuditLogExported(
            organizationId: $params->organizationId,
            format: $params->format,
            fromDate: $params->fromDate,
            toDate: $params->toDate,
        ));

        return match ($params->format) {
            'json' => $this->streamJson($query),
            default => $this->streamCsv($query),
        };
    }

    private function streamCsv($query): StreamedResponse
    {
        $filename = 'audit-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'uuid', 'event', 'event_category', 'description',
                'user_id', 'risk_level', 'ip_address', 'created_at',
            ]);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->uuid,
                        $log->event,
                        $log->event_category,
                        $log->description,
                        $log->user_id,
                        $log->risk_level,
                        $log->ip_address,
                        $log->created_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function streamJson($query): StreamedResponse
    {
        $filename = 'audit-log-'.now()->format('Y-m-d-His').'.json';

        return response()->streamDownload(function () use ($query) {
            echo '[';
            $first = true;

            $query->chunk(500, function ($logs) use (&$first) {
                foreach ($logs as $log) {
                    if (! $first) {
                        echo ',';
                    }
                    echo json_encode([
                        'uuid' => $log->uuid,
                        'event' => $log->event,
                        'event_category' => $log->event_category,
                        'description' => $log->description,
                        'user_id' => $log->user_id,
                        'risk_level' => $log->risk_level,
                        'ip_address' => $log->ip_address,
                        'created_at' => $log->created_at?->toIso8601String(),
                    ]);
                    $first = false;
                }
            });

            echo ']';
        }, $filename, ['Content-Type' => 'application/json']);
    }
}
