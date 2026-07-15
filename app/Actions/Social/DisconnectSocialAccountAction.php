<?php

declare(strict_types=1);

namespace App\Actions\Social;

use App\DTOs\Social\DisconnectSocialAccountData;
use App\Events\SocialAccountDisconnected;
use App\Models\Organization;
use App\Models\SocialAccount;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class DisconnectSocialAccountAction
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Disconnect all active accounts for a given platform within an organization.
     */
    public function executeForPlatform(Organization $organization, DisconnectSocialAccountData $data): int
    {
        $accounts = SocialAccount::where('organization_id', $organization->id)
            ->where('platform', $data->platform)
            ->get();

        $count = 0;
        foreach ($accounts as $account) {
            $this->disconnectAccount($account);
            $count++;
        }

        return $count;
    }

    /**
     * Disconnect a single social account (used from controller route binding).
     */
    public function executeSingle(SocialAccount $account): void
    {
        Gate::authorize('delete', $account);
        $this->disconnectAccount($account);
    }

    private function disconnectAccount(SocialAccount $account): void
    {
        $account->update(['status' => 'disconnected']);
        $account->delete();

        $this->auditService->logUserAction(
            event: 'social_account.disconnected',
            description: "Social account '{$account->account_name}' disconnected from {$account->platform}",
            subject: $account,
            data: ['platform' => $account->platform, 'account_name' => $account->account_name],
        );

        event(new SocialAccountDisconnected(
            organizationId: $account->organization_id,
            platform: $account->platform,
            accountName: $account->account_name,
        ));
    }
}
