<?php

namespace App\Livewire\Social;

use App\Actions\Organizations\SaveConnectionSettingsAction;
use App\Actions\Social\DisconnectSocialAccountAction;
use App\DTOs\Organizations\SaveConnectionSettingsData;
use App\DTOs\Social\DisconnectSocialAccountData;
use App\Livewire\Concerns\ResolvesCurrentOrganization;
use App\Models\Organization;
use App\Models\SocialAccount;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ConnectedAccounts extends Component
{
    use ResolvesCurrentOrganization;

    /** Platform whose settings panel is open for inline editing. */
    public ?string $managing = null;

    // Inline settings edit state
    public array $editGoals = [];

    public array $editFeatures = [];

    public array $editPermissions = [];

    public int $editAutonomy = 1;

    public bool $settingsSaved = false;

    #[Computed]
    public function orgId(): int
    {
        return (int) session('current_organization_id');
    }

    /**
     * Returns one entry per platform, with connection status and settings merged in.
     */
    #[Computed]
    public function platformStatus(): array
    {
        // No explicit organization_id filter needed: SocialAccount's
        // HasOrganizationScope trait applies it automatically from the session.
        $accounts = SocialAccount::withCount('conversations')
            ->with(['pages', 'connectionSettings'])
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('platform');

        $result = [];
        foreach (ConnectPlatformWizard::platforms() as $platform => $meta) {
            $platformAccounts = $accounts->get($platform, collect());
            $activeAccounts = $platformAccounts->where('status', 'active');
            $primary = $activeAccounts->first();

            $result[$platform] = array_merge($meta, [
                'accounts' => $platformAccounts,
                'connected' => $activeAccounts->isNotEmpty(),
                'account_count' => $activeAccounts->count(),
                'page_count' => $platformAccounts->sum(fn ($a) => $a->pages->count()),
                'last_synced' => $platformAccounts->max('last_synced_at'),
                'settings' => $primary?->connectionSettings,
                'primary' => $primary,
            ]);
        }

        return $result;
    }

    // ── Manage settings inline ────────────────────────────────────────────────

    public function openManage(string $platform): void
    {
        $this->managing = $platform;
        $this->settingsSaved = false;

        $account = SocialAccount::where('organization_id', $this->orgId)
            ->where('platform', $platform)
            ->where('status', 'active')
            ->with('connectionSettings')
            ->first();

        if ($settings = $account?->connectionSettings) {
            $this->editGoals = $settings->goals ?? [];
            $this->editFeatures = $settings->ai_features ?? [];
            $this->editPermissions = $settings->permissions ?? [];
            $this->editAutonomy = $settings->autonomy_level ?? 1;
        } else {
            $this->editGoals = [];
            $this->editFeatures = ['customer_support', 'lead_generation', 'reputation_monitoring'];
            $this->editPermissions = ['reply_comments', 'reply_messages', 'answer_faqs', 'create_support_tickets'];
            $this->editAutonomy = 1;
        }
    }

    public function closeManage(): void
    {
        $this->managing = null;
    }

    public function saveSettings(): void
    {
        $account = SocialAccount::where('organization_id', $this->orgId)
            ->where('platform', $this->managing)
            ->where('status', 'active')
            ->first();

        if (! $account) {
            return;
        }

        app(SaveConnectionSettingsAction::class)->execute($account, SaveConnectionSettingsData::fromArray([
            'goals' => $this->editGoals,
            'ai_features' => $this->editFeatures,
            'permissions' => $this->editPermissions,
            'autonomy_level' => $this->editAutonomy,
        ]));

        $this->settingsSaved = true;
        unset($this->platformStatus);

        $this->js('setTimeout(() => { $wire.settingsSaved = false }, 3000)');
    }

    // ── Disconnect ────────────────────────────────────────────────────────────

    public function disconnect(string $platform): void
    {
        // See ResolvesCurrentOrganization: session org context can be null
        // for this request even though the user is authenticated.
        $organization = $this->requireCurrentOrganization();
        app(DisconnectSocialAccountAction::class)->executeForPlatform($organization, DisconnectSocialAccountData::fromPlatform($platform));

        unset($this->platformStatus);
        session()->flash('success', ucfirst($platform).' disconnected.');
    }

    public function render()
    {
        return view('livewire.social.connected-accounts');
    }
}
