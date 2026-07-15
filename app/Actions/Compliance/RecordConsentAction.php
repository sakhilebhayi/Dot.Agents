<?php

namespace App\Actions\Compliance;

use App\Events\ConsentRecorded;
use App\Models\User;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class RecordConsentAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Record explicit user consent for a specific purpose.
     *
     * Required under GDPR Article 7 and POPIA Section 11.
     */
    public function execute(
        User $user,
        string $consentPurpose,
        bool $granted,
        string $version,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        // No Gate::authorize here — users always have the right to withdraw consent

        $consents = $user->consent_records ?? [];

        $consents[$consentPurpose] = [
            'granted' => $granted,
            'version' => $version,
            'recorded_at' => now()->toISOString(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ];

        $user->update(['consent_records' => $consents]);

        $action = $granted ? 'granted' : 'withdrawn';

        $this->auditService->logUserAction(
            event: "compliance.consent_{$action}",
            description: "User {$user->id} {$action} consent for purpose '{$consentPurpose}' (version {$version})",
            subject: $user,
        );

        event(new ConsentRecorded($user, $consentPurpose, $granted, $version));
    }

    /**
     * Check whether a user has active consent for a purpose.
     */
    public function hasConsent(User $user, string $purpose): bool
    {
        $record = ($user->consent_records ?? [])[$purpose] ?? null;

        return $record !== null && $record['granted'] === true;
    }
}
