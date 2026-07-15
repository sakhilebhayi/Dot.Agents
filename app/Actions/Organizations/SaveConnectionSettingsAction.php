<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\DTOs\Organizations\SaveConnectionSettingsData;
use App\Events\ConnectionSettingsSaved;
use App\Models\SocialAccount;
use App\Models\SocialConnectionSettings;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class SaveConnectionSettingsAction
{
    public function __construct(private readonly AuditService $auditService) {}

    public function execute(SocialAccount $account, SaveConnectionSettingsData $data): SocialConnectionSettings
    {
        Gate::authorize('update', $account);

        $cred = SocialConnectionSettings::updateOrCreate(
            ['social_account_id' => $account->id],
            [
                'organization_id' => $account->organization_id,
                'platform' => $account->platform,
                'goals' => $data->goals,
                'ai_features' => $data->aiFeatures,
                'permissions' => $data->permissions,
                'autonomy_level' => $data->autonomyLevel,
                'status' => 'active',
            ]
        );

        $this->auditService->logUserAction(
            event: 'social_connection_settings.saved',
            description: "Connection settings saved for {$account->platform} account '{$account->account_name}'",
            subject: $account,
            data: ['platform' => $account->platform],
        );

        event(new ConnectionSettingsSaved($cred));

        return $cred;
    }
}
