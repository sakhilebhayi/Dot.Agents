<?php

namespace App\Actions\Compliance;

use App\Events\UserDataExported;
use App\Models\User;
use App\Services\Governance\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class ExportUserDataAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Export all personal data for a user (GDPR Article 20 — Right to Data Portability).
     *
     * Collects profile, session, task, and audit data into a structured array
     * suitable for JSON export. The result is logged via AuditService.
     *
     * @param  User  $requester  Actor requesting the export (must be the subject or an admin).
     * @param  User  $subject  The user whose data will be exported.
     * @return array Structured personal data export payload.
     *
     * @throws AuthorizationException When requester is not authorized.
     */
    public function execute(User $requester, User $subject): array
    {
        Gate::authorize('export-own-data', [$requester, $subject]);

        $data = [
            'exported_at' => now()->toISOString(),
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'email' => $subject->email,
                'created_at' => $subject->created_at?->toISOString(),
                'updated_at' => $subject->updated_at?->toISOString(),
            ],
            'profile' => [
                'two_factor_enabled' => ! is_null($subject->two_factor_secret),
                'profile_photo_url' => $subject->profile_photo_url,
            ],
            'organizations' => $subject->organizations()
                ->select(['organizations.id', 'organizations.name', 'organizations.slug'])
                ->get()
                ->map(fn ($org) => [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'role' => $org->pivot->role,
                ])
                ->toArray(),
            'audit_activity' => $subject->auditLogs()
                ->select(['id', 'event', 'created_at'])
                ->latest()
                ->limit(500)
                ->get()
                ->map(fn ($log) => [
                    'event' => $log->event,
                    'created_at' => $log->created_at?->toISOString(),
                ])
                ->toArray(),
            'notifications' => $subject->notifications()
                ->select(['id', 'type', 'data', 'read_at', 'created_at'])
                ->latest()
                ->limit(200)
                ->get()
                ->map(fn ($n) => [
                    'type' => $n->type,
                    'data' => $n->data,
                    'read_at' => $n->read_at?->toISOString(),
                    'created_at' => $n->created_at?->toISOString(),
                ])
                ->toArray(),
        ];

        $this->auditService->logUserAction(
            event: 'compliance.data_exported',
            description: "GDPR data export requested for user {$subject->id} by user {$requester->id}",
            subject: $subject,
        );

        event(new UserDataExported($subject, $requester->id));

        return $data;
    }
}
