<?php

namespace App\Livewire\Social;

use App\Actions\Social\SaveSocialCredentialsAction;
use App\Livewire\Concerns\ManagesOAuthFlow;
use App\Models\Organization;
use App\Models\SocialAccount;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ConnectPlatformWizard extends Component
{
    use ManagesOAuthFlow;

    public int $step = 1;

    // Step 1
    public string $selectedPlatform = '';

    // Step 2
    public string $connectionMode = 'quick'; // quick | advanced

    public string $advClientId = '';

    public string $advClientSecret = '';

    // Step 3 — Business Goals
    public array $selectedGoals = [];

    // Step 4 — AI Capabilities & Permissions
    public array $enabledFeatures = [];

    public array $enabledPermissions = [];

    public int $autonomyLevel = 1;

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->enabledFeatures = ['customer_support', 'lead_generation', 'reputation_monitoring'];
        $this->enabledPermissions = ['reply_comments', 'reply_messages', 'answer_faqs', 'create_support_tickets'];
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function connectedPlatforms(): array
    {
        // No explicit organization_id filter needed: SocialAccount's
        // HasOrganizationScope trait applies it automatically from the session.
        return SocialAccount::where('status', 'active')
            ->pluck('platform')
            ->unique()
            ->toArray();
    }

    #[Computed]
    public function currentPlatformMeta(): array
    {
        return self::platforms()[$this->selectedPlatform] ?? [];
    }

    // ── Activate ──────────────────────────────────────────────────────────────

    public function activate(SaveSocialCredentialsAction $saveCredentials): void
    {
        $orgId = (int) session('current_organization_id');
        $org = Organization::findOrFail($orgId);

        if ($this->connectionMode === 'advanced' && $this->advClientId && $this->advClientSecret) {
            $saveCredentials->execute($org, $this->selectedPlatform, [
                'client_id' => $this->advClientId,
                'client_secret' => $this->advClientSecret,
            ], auth()->id());
        }

        session()->put("social_wizard_{$this->selectedPlatform}", [
            'goals' => $this->selectedGoals,
            'ai_features' => $this->enabledFeatures,
            'permissions' => $this->enabledPermissions,
            'autonomy_level' => $this->autonomyLevel,
        ]);

        $this->redirect(route('social.auth.redirect', ['platform' => $this->selectedPlatform]));
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.social.connect-platform-wizard');
    }
}
