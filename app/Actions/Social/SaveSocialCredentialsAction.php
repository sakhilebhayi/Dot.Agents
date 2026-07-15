<?php

namespace App\Actions\Social;

use App\Events\SocialCredentialsSaved;
use App\Models\Organization;
use App\Models\OrganizationSocialCredential;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class SaveSocialCredentialsAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Upsert the OAuth credentials for one platform within an organization.
     *
     * @param  array{client_id: string, client_secret: string, redirect_uri?: string|null}  $credentials
     */
    public function execute(Organization $organization, string $platform, array $credentials, int $updatedBy): OrganizationSocialCredential
    {
        Gate::authorize('update', $organization);

        $cred = OrganizationSocialCredential::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'platform' => $platform,
            ],
            [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'redirect_uri' => $credentials['redirect_uri'] ?? null,
                'updated_by' => $updatedBy,
            ]
        );

        $this->auditService->logUserAction(
            event: 'social_credentials.saved',
            description: "OAuth credentials saved for platform: {$platform}",
            data: ['platform' => $platform, 'organization_id' => $organization->id],
            subject: $organization,
        );

        event(new SocialCredentialsSaved($organization, $platform));

        return $cred;
    }

    /**
     * Remove stored credentials for a platform (revert to platform defaults).
     */
    public function delete(Organization $organization, string $platform): void
    {
        Gate::authorize('update', $organization);

        OrganizationSocialCredential::where('organization_id', $organization->id)
            ->where('platform', $platform)
            ->delete();

        $this->auditService->logUserAction(
            event: 'social_credentials.removed',
            description: "OAuth credentials removed for platform: {$platform}",
            data: ['platform' => $platform, 'organization_id' => $organization->id],
            subject: $organization,
        );
    }
}
