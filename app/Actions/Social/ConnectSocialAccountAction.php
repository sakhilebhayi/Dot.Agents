<?php

namespace App\Actions\Social;

use App\DTOs\Social\ConnectSocialAccountData;
use App\Events\SocialAccountConnected;
use App\Models\SocialAccount;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ConnectSocialAccountAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function execute(ConnectSocialAccountData $data): SocialAccount
    {
        Gate::authorize('create', [SocialAccount::class, $data->organizationId]);

        $account = SocialAccount::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $data->organizationId,
            'agent_deployment_id' => $data->agentDeploymentId,
            'platform' => $data->platform,
            'platform_account_id' => $data->platformAccountId,
            'account_name' => $data->accountName,
            'account_handle' => $data->accountHandle,
            'account_type' => $data->accountType,
            'avatar_url' => $data->avatarUrl,
            'access_token' => $data->accessToken,
            'refresh_token' => $data->refreshToken,
            'token_expires_at' => $data->tokenExpiresAt,
            'scopes' => $data->scopes,
            'settings' => $data->settings,
            'status' => 'active',
            'connected_at' => now(),
        ]);

        $this->auditService->logUserAction(
            event: 'social_account.connected',
            description: "Social account connected: {$data->accountName} ({$data->platform})",
            data: ['platform' => $data->platform, 'account_name' => $data->accountName],
            subject: $account,
        );

        event(new SocialAccountConnected($account));

        return $account;
    }
}
